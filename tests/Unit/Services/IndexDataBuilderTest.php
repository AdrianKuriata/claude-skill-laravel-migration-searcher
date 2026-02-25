<?php

namespace Tests\Unit\Services;

use DevSite\LaravelMigrationSearcher\Contracts\IndexDataBuilder as IndexDataBuilderContract;
use DevSite\LaravelMigrationSearcher\Services\IndexDataBuilder;
use Tests\TestCase;

class IndexDataBuilderTest extends TestCase
{
    protected IndexDataBuilderContract $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new IndexDataBuilder();
    }

    protected function sampleMigration(array $overrides = []): array
    {
        return array_merge([
            'filename' => '2024_01_15_100000_create_users_table.php',
            'filepath' => '/app/database/migrations/2024_01_15_100000_create_users_table.php',
            'relative_path' => 'database/migrations/2024_01_15_100000_create_users_table.php',
            'type' => 'default',
            'timestamp' => '2024_01_15_100000',
            'name' => 'create_users_table',
            'tables' => ['users' => ['operation' => 'CREATE', 'methods' => []]],
            'ddl_operations' => [['method' => 'id', 'params' => [], 'category' => 'column_create']],
            'dml_operations' => [],
            'raw_sql' => [],
            'dependencies' => [],
            'columns' => ['name' => ['type' => 'string', 'modifiers' => []]],
            'indexes' => [],
            'foreign_keys' => [],
            'methods_used' => ['id', 'string'],
            'has_data_modifications' => false,
            'complexity' => 2,
        ], $overrides);
    }

    protected function threeMigrations(): array
    {
        return [
            $this->sampleMigration(),
            $this->sampleMigration([
                'filename' => '2024_03_10_100000_data_migration.php',
                'timestamp' => '2024_03_10_100000',
                'name' => 'data_migration',
                'type' => 'default',
                'tables' => ['users' => ['operation' => 'DATA', 'methods' => []]],
                'columns' => [],
                'ddl_operations' => [],
                'dml_operations' => [
                    ['type' => 'UPDATE', 'table' => 'users', 'where_conditions' => [], 'columns_updated' => ['status'], 'has_db_raw' => false, 'db_raw_expressions' => [], 'data_preview' => ''],
                ],
                'has_data_modifications' => true,
                'complexity' => 3,
            ]),
            $this->sampleMigration([
                'filename' => '2024_02_01_100000_raw_sql.php',
                'timestamp' => '2024_02_01_100000',
                'name' => 'raw_sql',
                'type' => 'system',
                'tables' => [],
                'columns' => [],
                'ddl_operations' => [],
                'raw_sql' => [['type' => 'statement', 'sql' => 'CREATE INDEX idx ON users (email)', 'operation' => 'CREATE']],
                'has_data_modifications' => true,
                'complexity' => 4,
            ]),
        ];
    }

    // ── buildFullIndex ─────────────────────────────────────────────

    public function testBuildFullIndexReturnsSortedMigrations(): void
    {
        $data = $this->builder->buildFullIndex($this->threeMigrations());

        $timestamps = array_column($data['migrations'], 'timestamp');
        $sorted = $timestamps;
        sort($sorted);

        $this->assertSame($sorted, $timestamps);
    }

    public function testBuildFullIndexContainsMetadata(): void
    {
        $data = $this->builder->buildFullIndex($this->threeMigrations());

        $this->assertSame('Full Laravel Migrations Index', $data['title']);
        $this->assertArrayHasKey('generated_at', $data);
        $this->assertSame(3, $data['total_migrations']);
        $this->assertCount(3, $data['migrations']);
    }

    public function testBuildFullIndexEmptyInput(): void
    {
        $data = $this->builder->buildFullIndex([]);

        $this->assertSame(0, $data['total_migrations']);
        $this->assertEmpty($data['migrations']);
        $this->assertArrayHasKey('generated_at', $data);
    }

    // ── buildByTypeIndex ───────────────────────────────────────────

    public function testBuildByTypeIndexGroupsByType(): void
    {
        $data = $this->builder->buildByTypeIndex($this->threeMigrations());

        $this->assertSame('Migrations Index - Grouped by Type', $data['title']);
        $this->assertArrayHasKey('default', $data['groups']);
        $this->assertArrayHasKey('system', $data['groups']);
        $this->assertSame(2, $data['groups']['default']['count']);
        $this->assertSame(1, $data['groups']['system']['count']);
    }

    public function testBuildByTypeIndexSortsMigrationsWithinGroup(): void
    {
        $data = $this->builder->buildByTypeIndex($this->threeMigrations());

        $defaultTimestamps = array_column($data['groups']['default']['migrations'], 'timestamp');
        $sorted = $defaultTimestamps;
        sort($sorted);

        $this->assertSame($sorted, $defaultTimestamps);
    }

    public function testBuildByTypeIndexGroupKeysAreSorted(): void
    {
        $data = $this->builder->buildByTypeIndex($this->threeMigrations());

        $keys = array_keys($data['groups']);
        $sorted = $keys;
        sort($sorted);

        $this->assertSame($sorted, $keys);
    }

    public function testBuildByTypeIndexEmptyInput(): void
    {
        $data = $this->builder->buildByTypeIndex([]);

        $this->assertEmpty($data['groups']);
    }

    // ── buildByTableIndex ──────────────────────────────────────────

    public function testBuildByTableIndexGroupsByTable(): void
    {
        $data = $this->builder->buildByTableIndex($this->threeMigrations());

        $this->assertSame('Migrations Index - Grouped by Tables', $data['title']);
        $this->assertArrayHasKey('users', $data['tables']);
        $this->assertSame(2, $data['tables']['users']['count']);
    }

    public function testBuildByTableIndexTablesAreSortedAlphabetically(): void
    {
        $migrations = [
            $this->sampleMigration(['tables' => ['zebra' => ['operation' => 'CREATE', 'methods' => []]]]),
            $this->sampleMigration(['tables' => ['alpha' => ['operation' => 'CREATE', 'methods' => []]]]),
        ];

        $data = $this->builder->buildByTableIndex($migrations);
        $keys = array_keys($data['tables']);

        $this->assertSame(['alpha', 'zebra'], $keys);
    }

    public function testBuildByTableIndexMigrationsContainTableOperation(): void
    {
        $data = $this->builder->buildByTableIndex($this->threeMigrations());

        $firstMigration = $data['tables']['users']['migrations'][0];
        $this->assertArrayHasKey('table_operation', $firstMigration);
    }

    public function testBuildByTableIndexEmptyInput(): void
    {
        $data = $this->builder->buildByTableIndex([]);
        $this->assertEmpty($data['tables']);
    }

    // ── buildByOperationIndex ──────────────────────────────────────

    public function testBuildByOperationIndexGroupsByOperation(): void
    {
        $data = $this->builder->buildByOperationIndex($this->threeMigrations());

        $this->assertSame('Migrations Index - Grouped by Operations', $data['title']);
        $this->assertArrayHasKey('CREATE', $data['operations']);
        $this->assertArrayHasKey('DATA', $data['operations']);
        $this->assertSame('Table Creation', $data['operations']['CREATE']['description']);
    }

    public function testBuildByOperationIndexContainsAllOperationTypes(): void
    {
        $data = $this->builder->buildByOperationIndex([]);

        $expectedOps = ['CREATE', 'ALTER', 'DROP', 'DATA', 'RENAME'];
        foreach ($expectedOps as $op) {
            $this->assertArrayHasKey($op, $data['operations']);
        }
    }

    public function testBuildByOperationIndexMigrationsContainTargetTable(): void
    {
        $data = $this->builder->buildByOperationIndex($this->threeMigrations());

        $createMigrations = $data['operations']['CREATE']['migrations'];
        $this->assertNotEmpty($createMigrations);
        $this->assertArrayHasKey('target_table', $createMigrations[0]);
    }

    public function testBuildByOperationIndexContainsRawSqlSection(): void
    {
        $data = $this->builder->buildByOperationIndex($this->threeMigrations());

        $this->assertArrayHasKey('raw_sql', $data);
        $this->assertSame(1, $data['raw_sql']['count']);
        $this->assertCount(1, $data['raw_sql']['migrations']);
    }

    public function testBuildByOperationIndexEmptyInput(): void
    {
        $data = $this->builder->buildByOperationIndex([]);

        foreach ($data['operations'] as $op) {
            $this->assertSame(0, $op['count']);
            $this->assertEmpty($op['migrations']);
        }
        $this->assertSame(0, $data['raw_sql']['count']);
    }

    // ── buildStats ─────────────────────────────────────────────────

    public function testBuildStatsCalculatesCorrectly(): void
    {
        $data = $this->builder->buildStats($this->threeMigrations());

        $this->assertArrayHasKey('generated_at', $data);
        $this->assertSame(3, $data['total_migrations']);
        $this->assertSame(['default' => 2, 'system' => 1], $data['by_type']);
        $this->assertSame(4, $data['complexity']['max']);
        $this->assertSame(0, $data['complexity']['high_complexity']);
        $this->assertSame(2, $data['data_modifications']);
        $this->assertSame(1, $data['raw_sql_count']);
    }

    public function testBuildStatsComplexityAverage(): void
    {
        $data = $this->builder->buildStats($this->threeMigrations());

        $this->assertSame(3.0, $data['complexity']['average']);
    }

    public function testBuildStatsLimitsTablesTo50(): void
    {
        $migrations = [];
        for ($i = 0; $i < 60; $i++) {
            $migrations[] = $this->sampleMigration([
                'filename' => "m{$i}.php",
                'tables' => ["table_{$i}" => ['operation' => 'CREATE', 'methods' => []]],
                'complexity' => 1,
                'has_data_modifications' => false,
                'raw_sql' => [],
            ]);
        }

        $data = $this->builder->buildStats($migrations);

        $this->assertLessThanOrEqual(50, count($data['tables']));
    }

    public function testBuildStatsEmptyInput(): void
    {
        $data = $this->builder->buildStats([]);

        $this->assertSame(0, $data['total_migrations']);
        $this->assertEmpty($data['by_type']);
        $this->assertEmpty($data['tables']);
        $this->assertSame(0, $data['data_modifications']);
        $this->assertSame(0, $data['raw_sql_count']);
    }

    public function testBuildStatsHighComplexityCount(): void
    {
        $migrations = [
            $this->sampleMigration(['complexity' => 7]),
            $this->sampleMigration(['complexity' => 9]),
            $this->sampleMigration(['complexity' => 3]),
        ];

        $data = $this->builder->buildStats($migrations);

        $this->assertSame(2, $data['complexity']['high_complexity']);
    }

    public function testBuildStatsTablesSortedByMigrationCount(): void
    {
        $migrations = [
            $this->sampleMigration([
                'tables' => [
                    'orders' => ['operation' => 'CREATE', 'methods' => []],
                ],
            ]),
            $this->sampleMigration([
                'tables' => [
                    'users' => ['operation' => 'CREATE', 'methods' => []],
                ],
            ]),
            $this->sampleMigration([
                'tables' => [
                    'users' => ['operation' => 'ALTER', 'methods' => []],
                ],
            ]),
        ];

        $data = $this->builder->buildStats($migrations);

        $keys = array_keys($data['tables']);
        $this->assertSame('users', $keys[0]);
    }
}
