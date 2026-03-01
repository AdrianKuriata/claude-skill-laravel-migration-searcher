<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use DevSite\LaravelMigrationSearcher\Contracts\Services\IndexDataBuilder as IndexDataBuilderContract;

class IndexDataBuilder implements IndexDataBuilderContract
{
    /** @var array<string, string> */
    private const array OPERATION_LABELS = [
        'CREATE' => 'Table Creation',
        'ALTER' => 'Structure Modifications',
        'DROP' => 'Table Deletion',
        'DATA' => 'Data Modifications',
        'RENAME' => 'Renaming',
    ];

    /**
     * @param list<array<string, mixed>> $migrations
     * @return array<string, mixed>
     */
    public function buildFullIndex(array $migrations): array
    {
        $sorted = collect($migrations)->sortBy('timestamp')->values()->all();

        return [
            'title' => 'Full Laravel Migrations Index',
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'total_migrations' => count($migrations),
            'migrations' => $sorted,
        ];
    }

    /**
     * @param list<array<string, mixed>> $migrations
     * @return array<string, mixed>
     */
    public function buildByTypeIndex(array $migrations): array
    {
        $grouped = collect($migrations)->groupBy('type');

        /** @var array<string, array{count: int, migrations: list<array<string, mixed>>}> $groups */
        $groups = [];
        foreach ($grouped->sortKeys() as $type => $typeMigrations) {
            $sorted = $typeMigrations->sortBy('timestamp')->values()->all();
            $groups[(string) $type] = [
                'count' => count($sorted),
                'migrations' => $sorted,
            ];
        }

        return [
            'title' => 'Migrations Index - Grouped by Type',
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'groups' => $groups,
        ];
    }

    /**
     * @param list<array<string, mixed>> $migrations
     * @return array<string, mixed>
     */
    public function buildByTableIndex(array $migrations): array
    {
        /** @var array<string, list<array<string, mixed>>> $tablesMigrations */
        $tablesMigrations = [];

        foreach ($migrations as $migration) {
            /** @var array<string, array{operation: string, methods: list<string>}> $tables */
            $tables = $migration['tables'] ?? [];
            foreach ($tables as $table => $tableInfo) {
                if (!isset($tablesMigrations[$table])) {
                    $tablesMigrations[$table] = [];
                }
                $tablesMigrations[$table][] = array_merge($migration, ['table_operation' => $tableInfo['operation']]);
            }
        }

        ksort($tablesMigrations);

        /** @var array<string, array{count: int, migrations: list<array<string, mixed>>}> $tables */
        $tables = [];
        foreach ($tablesMigrations as $table => $tableMigrations) {
            $tables[$table] = [
                'count' => count($tableMigrations),
                'migrations' => $tableMigrations,
            ];
        }

        return [
            'title' => 'Migrations Index - Grouped by Tables',
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'tables' => $tables,
        ];
    }

    /**
     * @param list<array<string, mixed>> $migrations
     * @return array<string, mixed>
     */
    public function buildByOperationIndex(array $migrations): array
    {
        /** @var array<string, array{description: string, count: int, migrations: list<array<string, mixed>>}> $operations */
        $operations = [];

        foreach (self::OPERATION_LABELS as $op => $description) {
            /** @var list<array<string, mixed>> $opMigrations */
            $opMigrations = [];

            foreach ($migrations as $migration) {
                /** @var array<string, array{operation: string, methods: list<string>}> $tables */
                $tables = $migration['tables'] ?? [];
                foreach ($tables as $table => $tableInfo) {
                    if ($tableInfo['operation'] === $op) {
                        $opMigrations[] = array_merge($migration, [
                            'target_table' => $table,
                            'operation' => $op,
                        ]);
                    }
                }
            }

            $operations[$op] = [
                'description' => $description,
                'count' => count($opMigrations),
                'migrations' => $opMigrations,
            ];
        }

        $rawSqlMigrations = collect($migrations)
            ->filter(fn (array $m): bool => !empty($m['raw_sql']))
            ->values()
            ->all();

        return [
            'title' => 'Migrations Index - Grouped by Operations',
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'operations' => $operations,
            'raw_sql' => [
                'count' => count($rawSqlMigrations),
                'migrations' => $rawSqlMigrations,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $migrations
     * @return array<string, mixed>
     */
    public function buildStats(array $migrations): array
    {
        $collection = collect($migrations);

        /** @var array<string, int> $byType */
        $byType = $collection->groupBy('type')->map(fn ($group): int => $group->count())->sortKeys()->all();

        $avgComplexity = count($migrations) > 0
            ? round((float) $collection->avg('complexity'), 2)
            : 0;

        return [
            'generated_at' => now()->toIso8601String(),
            'total_migrations' => count($migrations),
            'by_type' => $byType,
            'tables' => $this->getTableStats($migrations),
            'complexity' => [
                'average' => $avgComplexity,
                'max' => is_int($maxComplexity = $collection->max('complexity')) ? $maxComplexity : 0,
                'high_complexity' => $collection->where('complexity', '>=', 7)->count(),
            ],
            'data_modifications' => $collection->where('has_data_modifications', true)->count(),
            'raw_sql_count' => $collection->filter(fn (array $m): bool => !empty($m['raw_sql']))->count(),
        ];
    }

    /**
     * @param list<array<string, mixed>> $migrations
     * @return array<string, array{migrations_count: int, operations: array<string, int>}>
     */
    protected function getTableStats(array $migrations): array
    {
        /** @var array<string, array{migrations_count: int, operations: array<string, int>}> $tables */
        $tables = [];

        foreach ($migrations as $migration) {
            /** @var array<string, array{operation: string, methods: list<string>}> $migrationTables */
            $migrationTables = $migration['tables'] ?? [];
            foreach ($migrationTables as $table => $tableInfo) {
                if (!isset($tables[$table])) {
                    $tables[$table] = [
                        'migrations_count' => 0,
                        'operations' => [],
                    ];
                }
                $tables[$table]['migrations_count']++;
                $op = $tableInfo['operation'];
                $tables[$table]['operations'][$op] = ($tables[$table]['operations'][$op] ?? 0) + 1;
            }
        }

        uasort($tables, fn (array $a, array $b): int => $b['migrations_count'] <=> $a['migrations_count']);

        return array_slice($tables, 0, 50);
    }
}
