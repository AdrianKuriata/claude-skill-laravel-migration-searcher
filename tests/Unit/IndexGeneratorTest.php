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

    public function testGenerateAllCreatesAllFiveFiles(): void
    {
        $this->generator->setMigrations($this->sampleMigrations());
        $this->generator->generateAll();

        $this->assertFileExists($this->outputPath . '/index-full.md');
        $this->assertFileExists($this->outputPath . '/index-by-type.md');
        $this->assertFileExists($this->outputPath . '/index-by-table.md');
        $this->assertFileExists($this->outputPath . '/index-by-operation.md');
        $this->assertFileExists($this->outputPath . '/stats.json');
    }

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

        $this->assertStringContainsString('## Tabela: `users`', $content);
    }

    public function testByTableSortedAlphabetically(): void
    {
        $migrations = $this->sampleMigrations();
        // Add a table that should appear before 'users' alphabetically
        $migrations[0]['tables']['accounts'] = ['operation' => 'CREATE', 'methods' => []];
        $this->generator->setMigrations($migrations);
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-by-table.md');

        $posAccounts = strpos($content, '## Tabela: `accounts`');
        $posUsers = strpos($content, '## Tabela: `users`');

        $this->assertLessThan($posUsers, $posAccounts);
    }

    // ── index-by-operation.md ───────────────────────────────────────

    public function testByOperationGroupsByOperation(): void
    {
        $this->generator->setMigrations($this->sampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-by-operation.md');

        $this->assertStringContainsString('Tworzenie Tabel (CREATE)', $content);
        $this->assertStringContainsString('Modyfikacje Danych (DATA)', $content);
    }

    public function testByOperationRawSqlSection(): void
    {
        $this->generator->setMigrations($this->sampleMigrations());
        $this->generator->generateAll();

        $content = file_get_contents($this->outputPath . '/index-by-operation.md');

        $this->assertStringContainsString('## Raw SQL', $content);
    }

    // ── stats.json ──────────────────────────────────────────────────

    public function testStatsJsonIsValidJson(): void
    {
        $this->generator->setMigrations($this->sampleMigrations());
        $this->generator->generateAll();

        $json = file_get_contents($this->outputPath . '/stats.json');
        $decoded = json_decode($json, true);

        $this->assertNotNull($decoded);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

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
        $this->assertStringContainsString('Grupowanie po Tabelach', $content);
    }
}
