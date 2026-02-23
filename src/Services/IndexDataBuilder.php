<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use DevSite\LaravelMigrationSearcher\Contracts\IndexDataBuilderInterface;

class IndexDataBuilder implements IndexDataBuilderInterface
{
    private const OPERATION_LABELS = [
        'CREATE' => 'Table Creation',
        'ALTER' => 'Structure Modifications',
        'DROP' => 'Table Deletion',
        'DATA' => 'Data Modifications',
        'RENAME' => 'Renaming',
    ];

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

    public function buildByTypeIndex(array $migrations): array
    {
        $grouped = collect($migrations)->groupBy('type')->sortKeys();

        $groups = [];
        foreach ($grouped as $type => $typeMigrations) {
            $sorted = $typeMigrations->sortBy('timestamp')->values()->all();
            $groups[$type] = [
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

    public function buildByTableIndex(array $migrations): array
    {
        $tablesMigrations = [];

        foreach ($migrations as $migration) {
            foreach ($migration['tables'] as $table => $tableInfo) {
                if (!isset($tablesMigrations[$table])) {
                    $tablesMigrations[$table] = [];
                }
                $tablesMigrations[$table][] = array_merge($migration, ['table_operation' => $tableInfo['operation']]);
            }
        }

        ksort($tablesMigrations);

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

    public function buildByOperationIndex(array $migrations): array
    {
        $operations = [];

        foreach (self::OPERATION_LABELS as $op => $description) {
            $opMigrations = [];

            foreach ($migrations as $migration) {
                foreach ($migration['tables'] as $table => $tableInfo) {
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
            ->filter(fn($m) => !empty($m['raw_sql']))
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

    public function buildStats(array $migrations): array
    {
        $collection = collect($migrations);

        return [
            'generated_at' => now()->toIso8601String(),
            'total_migrations' => count($migrations),
            'by_type' => $collection->groupBy('type')->map->count()->sortKeys()->all(),
            'tables' => $this->getTableStats($migrations),
            'complexity' => [
                'average' => count($migrations) > 0 ? round($collection->avg('complexity'), 2) : 0,
                'max' => $collection->max('complexity') ?? 0,
                'high_complexity' => $collection->where('complexity', '>=', 7)->count(),
            ],
            'data_modifications' => $collection->where('has_data_modifications', true)->count(),
            'raw_sql_count' => $collection->filter(fn($m) => !empty($m['raw_sql']))->count(),
        ];
    }

    protected function getTableStats(array $migrations): array
    {
        $tables = [];

        foreach ($migrations as $migration) {
            foreach ($migration['tables'] as $table => $tableInfo) {
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

        uasort($tables, fn($a, $b) => $b['migrations_count'] <=> $a['migrations_count']);

        return array_slice($tables, 0, 50);
    }
}
