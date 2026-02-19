<?php

namespace Tests\Unit;

use DevSite\LaravelMigrationSearcher\Services\IndexGenerator;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class IndexGeneratorTest extends TestCase
{
    protected string $outputPath;
    protected IndexGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputPath = sys_get_temp_dir() . '/index-generator-test-' . uniqid();
        $this->generator = new IndexGenerator($this->outputPath);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->outputPath)) {
            File::deleteDirectory($this->outputPath);
        }
        parent::tearDown();
    }

    protected function sampleMigrations(): array
    {
        return [
            [
                'filename' => '2024_01_15_100000_create_users_table.php',
                'filepath' => '/app/database/migrations/2024_01_15_100000_create_users_table.php',
                'relative_path' => 'database/migrations/2024_01_15_100000_create_users_table.php',
                'type' => 'default',
                'timestamp' => '2024_01_15_100000',
                'name' => 'create_users_table',
                'tables' => [
                    'users' => ['operation' => 'CREATE', 'methods' => []],
                ],
                'ddl_operations' => [
                    ['method' => 'id', 'params' => [], 'category' => 'column_create'],
                    ['method' => 'string', 'params' => ["'name'"], 'category' => 'column_create'],
                    ['method' => 'string', 'params' => ["'email'"], 'category' => 'column_create'],
                    ['method' => 'foreign', 'params' => ["'role_id'"], 'category' => 'foreign_key'],
                ],
                'dml_operations' => [],
                'raw_sql' => [],
                'dependencies' => [
                    'foreign_keys' => [
                        ['column' => 'role_id', 'references' => 'id', 'on_table' => 'roles'],
                    ],
                ],
                'columns' => [
                    'name' => ['type' => 'string', 'modifiers' => []],
                    'email' => ['type' => 'string', 'modifiers' => ['unique']],
                ],
                'indexes' => [],
                'foreign_keys' => [
                    ['column' => 'role_id', 'references' => 'id', 'on_table' => 'roles'],
                ],
                'methods_used' => ['id', 'string', 'foreign', 'timestamps'],
                'has_data_modifications' => false,
                'complexity' => 3,
            ],
            [
                'filename' => '2024_03_10_100000_data_migration.php',
                'filepath' => '/app/database/migrations/2024_03_10_100000_data_migration.php',
                'relative_path' => 'database/migrations/2024_03_10_100000_data_migration.php',
                'type' => 'default',
                'timestamp' => '2024_03_10_100000',
                'name' => 'data_migration',
                'tables' => [
                    'users' => ['operation' => 'DATA', 'methods' => []],
                ],
                'ddl_operations' => [],
                'dml_operations' => [
                    [
                        'type' => 'UPDATE',
                        'table' => 'users',
                        'where_conditions' => ['is_active = false'],
                        'columns_updated' => ['status'],
                        'has_db_raw' => false,
                        'db_raw_expressions' => [],
                        'data_preview' => "['status' => 'inactive']",
                    ],
                ],
                'raw_sql' => [],
                'dependencies' => [],
                'columns' => [],
                'indexes' => [],
                'foreign_keys' => [],
                'methods_used' => [],
                'has_data_modifications' => true,
                'complexity' => 2,
            ],
            [
                'filename' => '2024_02_01_100000_raw_sql.php',
                'filepath' => '/app/database/migrations/2024_02_01_100000_raw_sql.php',
                'relative_path' => 'database/migrations/2024_02_01_100000_raw_sql.php',
                'type' => 'default',
                'timestamp' => '2024_02_01_100000',
                'name' => 'raw_sql',
                'tables' => [],
                'ddl_operations' => [],
                'dml_operations' => [],
                'raw_sql' => [
                    [
                        'type' => 'statement',
                        'sql' => 'CREATE INDEX idx_email ON users (email)',
                        'operation' => 'CREATE',
                    ],
                ],
                'dependencies' => [],
                'columns' => [],
                'indexes' => [],
                'foreign_keys' => [],
                'methods_used' => [],
                'has_data_modifications' => true,
                'complexity' => 4,
            ],
        ];
    }

    // ── File Generation ─────────────────────────────────────────────

    public function testGenerateAllReturnsCorrectPaths(): void
    {
        $this->generator->setMigrations($this->sampleMigrations());
        $generated = $this->generator->generateAll();

        $this->assertArrayHasKey('full', $generated);
        $this->assertArrayHasKey('by_type', $generated);
        $this->assertArrayHasKey('by_table', $generated);
        $this->assertArrayHasKey('by_operation', $generated);
        $this->assertArrayHasKey('stats', $generated);

        foreach ($generated as $path) {
            $this->assertFileExists($path);
        }
    }

    public function testCreatesOutputDirectoryIfMissing(): void
    {
        $this->assertDirectoryDoesNotExist($this->outputPath);

        $this->generator->setMigrations([]);
        $this->generator->generateAll();

        $this->assertDirectoryExists($this->outputPath);
    }

    // ── index-full.md ───────────────────────────────────────────────

    public function testFullIndexContainsAllMigrations(): void
    {
        $migrations = $this->sampleMigrations();
        $this->generator->setMigrations($migrations);
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-full.md');

        foreach ($migrations as $m) {
            $this->assertStringContainsString($m['filename'], $content);
        }
    }

    public function testFullIndexSortedChronologically(): void
    {
        $migrations = $this->sampleMigrations();
        $this->generator->setMigrations($migrations);
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-full.md');

        // 2024_01_15 should appear before 2024_02_01 which is before 2024_03_10
        $pos1 = strpos($content, '2024_01_15_100000_create_users_table.php');
        $pos2 = strpos($content, '2024_02_01_100000_raw_sql.php');
        $pos3 = strpos($content, '2024_03_10_100000_data_migration.php');

        $this->assertLessThan($pos2, $pos1);
        $this->assertLessThan($pos3, $pos2);
    }

    public function testFullIndexContainsDmlDetails(): void
    {
        $this->generator->setMigrations($this->sampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-full.md');

        $this->assertStringContainsString('DML', $content);
        $this->assertStringContainsString('UPDATE', $content);
    }

    public function testFullIndexContainsRawSqlBlocks(): void
    {
        $this->generator->setMigrations($this->sampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-full.md');

        $this->assertStringContainsString('Raw SQL', $content);
        $this->assertStringContainsString('```sql', $content);
    }

    public function testFullIndexContainsForeignKeys(): void
    {
        $this->generator->setMigrations($this->sampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-full.md');

        $this->assertStringContainsString('Foreign Keys', $content);
        $this->assertStringContainsString('role_id', $content);
        $this->assertStringContainsString('roles', $content);
    }

    // ── index-by-table.md ───────────────────────────────────────────

    public function testByTableGroupsByTableName(): void
    {
        $this->generator->setMigrations($this->sampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-by-table.md');

        $this->assertStringContainsString('## Table: `users`', $content);
    }

    public function testByTableSortedAlphabetically(): void
    {
        $migrations = $this->sampleMigrations();
        // Add a table that should appear before 'users' alphabetically
        $migrations[0]['tables']['accounts'] = ['operation' => 'CREATE', 'methods' => []];
        $this->generator->setMigrations($migrations);
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-by-table.md');

        $posAccounts = strpos($content, '## Table: `accounts`');
        $posUsers = strpos($content, '## Table: `users`');

        $this->assertLessThan($posUsers, $posAccounts);
    }

    // ── index-by-operation.md ───────────────────────────────────────

    public function testByOperationGroupsByOperation(): void
    {
        $this->generator->setMigrations($this->sampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-by-operation.md');

        $this->assertStringContainsString('Table Creation (CREATE)', $content);
        $this->assertStringContainsString('Data Modifications (DATA)', $content);
    }

    public function testByOperationRawSqlSection(): void
    {
        $this->generator->setMigrations($this->sampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-by-operation.md');

        $this->assertStringContainsString('## Raw SQL', $content);
    }

    // ── stats.json ──────────────────────────────────────────────────

    public function testStatsJsonStructure(): void
    {
        $this->generator->setMigrations($this->sampleMigrations());
        $this->generator->generateAll();

        $stats = json_decode(file_get_contents($this->outputPath . '/stats.json'), true);

        $requiredKeys = [
            'generated_at',
            'total_migrations',
            'by_type',
            'tables',
            'complexity',
            'data_modifications',
            'raw_sql_count',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $stats, "Missing key: {$key}");
        }
    }

    public function testStatsJsonTotalCount(): void
    {
        $migrations = $this->sampleMigrations();
        $this->generator->setMigrations($migrations);
        $this->generator->generateAll();

        $stats = json_decode(file_get_contents($this->outputPath . '/stats.json'), true);

        $this->assertSame(count($migrations), $stats['total_migrations']);
    }

    public function testStatsJsonComplexityStats(): void
    {
        $this->generator->setMigrations($this->sampleMigrations());
        $this->generator->generateAll();

        $stats = json_decode(file_get_contents($this->outputPath . '/stats.json'), true);

        $this->assertArrayHasKey('average', $stats['complexity']);
        $this->assertArrayHasKey('max', $stats['complexity']);
        $this->assertArrayHasKey('high_complexity', $stats['complexity']);

        $this->assertIsNumeric($stats['complexity']['average']);
        $this->assertSame(4, $stats['complexity']['max']);
    }

    public function testStatsJsonTableStatsTop50Limit(): void
    {
        $migrations = [];
        for ($i = 0; $i < 60; $i++) {
            $migrations[] = [
                'filename' => "2024_01_{$i}_000000_m{$i}.php",
                'filepath' => "/app/m{$i}.php",
                'relative_path' => "m{$i}.php",
                'type' => 'default',
                'timestamp' => "2024_01_{$i}_000000",
                'name' => "m{$i}",
                'tables' => [
                    "table_{$i}" => ['operation' => 'CREATE', 'methods' => []],
                ],
                'ddl_operations' => [],
                'dml_operations' => [],
                'raw_sql' => [],
                'dependencies' => [],
                'columns' => [],
                'indexes' => [],
                'foreign_keys' => [],
                'methods_used' => [],
                'has_data_modifications' => false,
                'complexity' => 1,
            ];
        }

        $this->generator->setMigrations($migrations);
        $this->generator->generateAll();

        $stats = json_decode(file_get_contents($this->outputPath . '/stats.json'), true);

        $this->assertLessThanOrEqual(50, count($stats['tables']));
    }

    // ── Edge Cases ──────────────────────────────────────────────────

    public function testEmptyMigrationsArray(): void
    {
        $this->generator->setMigrations([]);
        $generated = $this->generator->generateAll();

        $this->assertCount(5, $generated);

        foreach ($generated as $path) {
            $this->assertFileExists($path);
        }

        $stats = json_decode(file_get_contents($this->outputPath . '/stats.json'), true);
        $this->assertSame(0, $stats['total_migrations']);
    }

    public function testMigrationWithNoTables(): void
    {
        $migrations = [
            [
                'filename' => '2024_01_01_000000_empty.php',
                'filepath' => '/app/empty.php',
                'relative_path' => 'empty.php',
                'type' => 'default',
                'timestamp' => '2024_01_01_000000',
                'name' => 'empty',
                'tables' => [],
                'ddl_operations' => [],
                'dml_operations' => [],
                'raw_sql' => [],
                'dependencies' => [],
                'columns' => [],
                'indexes' => [],
                'foreign_keys' => [],
                'methods_used' => [],
                'has_data_modifications' => false,
                'complexity' => 1,
            ],
        ];

        $this->generator->setMigrations($migrations);
        $generated = $this->generator->generateAll();

        $content = file_get_contents($generated['by_table']);
        // Should still be a valid file, just with no table sections
        $this->assertStringContainsString('Grouped by Tables', $content);
    }

    // ── Full sample migrations helper ───────────────────────────────

    protected function fullSampleMigrations(): array
    {
        return array_merge($this->sampleMigrations(), [
            [
                'filename' => '2024_04_01_100000_system_migration.php',
                'filepath' => '/app/database/migrations/2024_04_01_100000_system_migration.php',
                'relative_path' => 'database/migrations/2024_04_01_100000_system_migration.php',
                'type' => 'system',
                'timestamp' => '2024_04_01_100000',
                'name' => 'system_migration',
                'tables' => [
                    'settings' => ['operation' => 'CREATE', 'methods' => []],
                ],
                'ddl_operations' => [],
                'dml_operations' => [],
                'raw_sql' => [],
                'dependencies' => [],
                'columns' => ['key' => ['type' => 'string', 'modifiers' => []]],
                'indexes' => [],
                'foreign_keys' => [],
                'methods_used' => [],
                'has_data_modifications' => true,
                'complexity' => 1,
            ],
            [
                'filename' => '2024_05_01_100000_eloquent_dml.php',
                'filepath' => '/app/database/migrations/2024_05_01_100000_eloquent_dml.php',
                'relative_path' => 'database/migrations/2024_05_01_100000_eloquent_dml.php',
                'type' => 'default',
                'timestamp' => '2024_05_01_100000',
                'name' => 'eloquent_dml',
                'tables' => [
                    'posts' => ['operation' => 'DATA', 'methods' => []],
                ],
                'ddl_operations' => [],
                'dml_operations' => [
                    [
                        'type' => 'INSERT',
                        'model' => 'Post',
                        'method' => 'Eloquent::create',
                        'note' => 'Static Model::create() call',
                    ],
                    [
                        'type' => 'UPDATE/INSERT',
                        'variable' => '$post',
                        'method' => 'Eloquent->save()',
                        'note' => 'Model save - may be INSERT or UPDATE',
                    ],
                    [
                        'type' => 'INSERT',
                        'variable' => '$user',
                        'relation' => 'posts',
                        'method' => 'Eloquent->relation()->create()',
                        'note' => 'Record creation through posts relationship',
                    ],
                    [
                        'type' => 'LOOP',
                        'method' => 'foreach',
                        'operations_in_loop' => ['save() na $item', 'delete()'],
                        'note' => 'Loop operations: save(), delete()',
                    ],
                ],
                'raw_sql' => [],
                'dependencies' => [
                    'requires' => ['create_posts_table'],
                    'depends_on' => ['create_users_table'],
                ],
                'columns' => [],
                'indexes' => [],
                'foreign_keys' => [],
                'methods_used' => [],
                'has_data_modifications' => true,
                'complexity' => 5,
            ],
            [
                'filename' => '2024_06_01_100000_db_raw_update.php',
                'filepath' => '/app/database/migrations/2024_06_01_100000_db_raw_update.php',
                'relative_path' => 'database/migrations/2024_06_01_100000_db_raw_update.php',
                'type' => 'default',
                'timestamp' => '2024_06_01_100000',
                'name' => 'db_raw_update',
                'tables' => [
                    'orders' => ['operation' => 'DATA', 'methods' => []],
                ],
                'ddl_operations' => [],
                'dml_operations' => [
                    [
                        'type' => 'UPDATE',
                        'table' => 'orders',
                        'where_conditions' => ['status = pending'],
                        'columns_updated' => ['total'],
                        'has_db_raw' => true,
                        'db_raw_expressions' => ['CASE WHEN discount > 0 THEN total * 0.9 ELSE total END'],
                        'data_preview' => "['total' => DB::raw('...')]",
                    ],
                ],
                'raw_sql' => [],
                'dependencies' => [],
                'columns' => [],
                'indexes' => [],
                'foreign_keys' => [],
                'methods_used' => [],
                'has_data_modifications' => true,
                'complexity' => 4,
            ],
            [
                'filename' => '2024_07_01_100000_data_no_raw.php',
                'filepath' => '/app/database/migrations/2024_07_01_100000_data_no_raw.php',
                'relative_path' => 'database/migrations/2024_07_01_100000_data_no_raw.php',
                'type' => 'default',
                'timestamp' => '2024_07_01_100000',
                'name' => 'data_no_raw',
                'tables' => [
                    'tags' => ['operation' => 'DATA', 'methods' => []],
                ],
                'ddl_operations' => [],
                'dml_operations' => [
                    [
                        'type' => 'UPDATE',
                        'table' => 'tags',
                        'where_conditions' => ['active = true'],
                        'columns_updated' => ['name'],
                        'has_db_raw' => false,
                        'db_raw_expressions' => [],
                        'data_preview' => "['name' => 'updated']",
                    ],
                ],
                'raw_sql' => [],
                'dependencies' => [],
                'columns' => [],
                'indexes' => [],
                'foreign_keys' => [],
                'methods_used' => [],
                'has_data_modifications' => true,
                'complexity' => 2,
            ],
            [
                'filename' => '2024_08_01_100000_alter_with_columns.php',
                'filepath' => '/app/database/migrations/2024_08_01_100000_alter_with_columns.php',
                'relative_path' => 'database/migrations/2024_08_01_100000_alter_with_columns.php',
                'type' => 'default',
                'timestamp' => '2024_08_01_100000',
                'name' => 'alter_with_columns',
                'tables' => [
                    'profiles' => ['operation' => 'ALTER', 'methods' => []],
                ],
                'ddl_operations' => [
                    ['method' => 'string', 'params' => ["'bio'"], 'category' => 'column_create'],
                ],
                'dml_operations' => [],
                'raw_sql' => [],
                'dependencies' => [],
                'columns' => ['bio' => ['type' => 'string', 'modifiers' => ['nullable']]],
                'indexes' => [],
                'foreign_keys' => [],
                'methods_used' => ['string'],
                'has_data_modifications' => false,
                'complexity' => 2,
            ],
        ]);
    }

    // ── By-type index ───────────────────────────────────────────────

    public function testByTypeIndexContainsSystemType(): void
    {
        $this->generator->setMigrations($this->fullSampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-by-type.md');

        $this->assertStringContainsString('system_migration.php', $content);
    }

    public function testByTypeIndexShowsNoMigrationsMessage(): void
    {
        $this->generator->setMigrations([]);
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-by-type.md');

        $this->assertStringContainsString('*No migrations found*', $content);
    }

    public function testByTypeIndexGroupsDynamically(): void
    {
        $this->generator->setMigrations($this->sampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-by-type.md');

        $this->assertStringContainsString('## default', $content);
    }

    // ── Full index — DML branches ───────────────────────────────────

    public function testFullIndexContainsEloquentModelDml(): void
    {
        $this->generator->setMigrations($this->fullSampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-full.md');

        $this->assertStringContainsString('Post::Eloquent::create', $content);
    }

    public function testFullIndexContainsVariableDml(): void
    {
        $this->generator->setMigrations($this->fullSampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-full.md');

        $this->assertStringContainsString('$post->Eloquent->save()', $content);
    }

    public function testFullIndexContainsVariableDmlWithRelation(): void
    {
        $this->generator->setMigrations($this->fullSampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-full.md');

        $this->assertStringContainsString('relation: posts', $content);
    }

    public function testFullIndexContainsLoopDml(): void
    {
        $this->generator->setMigrations($this->fullSampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-full.md');

        $this->assertStringContainsString('LOOP', $content);
        $this->assertStringContainsString('foreach', $content);
    }

    public function testFullIndexContainsDbRawExpressions(): void
    {
        $this->generator->setMigrations($this->fullSampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-full.md');

        $this->assertStringContainsString('Uses DB::raw', $content);
        $this->assertStringContainsString('CASE WHEN', $content);
    }

    public function testFullIndexContainsDataPreviewWithoutDbRaw(): void
    {
        $this->generator->setMigrations($this->fullSampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-full.md');

        $this->assertStringContainsString("Data: ['name' => 'updated']", $content);
    }

    public function testFullIndexContainsNestedDependencies(): void
    {
        $this->generator->setMigrations($this->fullSampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-full.md');

        $this->assertStringContainsString('Dependencies', $content);
        $this->assertStringContainsString('requires', $content);
    }

    // ── By-operation index ──────────────────────────────────────────

    public function testByOperationAlterWithColumns(): void
    {
        $this->generator->setMigrations($this->fullSampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-by-operation.md');

        $this->assertStringContainsString('Affected columns', $content);
        $this->assertStringContainsString('bio', $content);
    }

    public function testByOperationDataWithWhereConditions(): void
    {
        $this->generator->setMigrations($this->fullSampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-by-operation.md');

        $this->assertStringContainsString('WHERE:', $content);
    }

    public function testFormatMigrationCompactModifiesDataWarning(): void
    {
        $this->generator->setMigrations($this->fullSampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-by-type.md');

        $this->assertStringContainsString('Modifies data', $content);
    }
}
