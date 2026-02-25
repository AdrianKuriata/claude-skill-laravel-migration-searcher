<?php

namespace Tests\Unit\Renderers;

use DevSite\LaravelMigrationSearcher\Services\IndexDataBuilder;
use DevSite\LaravelMigrationSearcher\Renderers\JsonRenderer;
use Tests\TestCase;

class JsonRendererTest extends TestCase
{
    protected JsonRenderer $renderer;
    protected IndexDataBuilder $dataBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new JsonRenderer();
        $this->dataBuilder = new IndexDataBuilder();
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

    protected function sampleMigrations(): array
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
                    ['type' => 'UPDATE', 'table' => 'users', 'where_conditions' => ['active = true'], 'columns_updated' => ['status'], 'has_db_raw' => false, 'db_raw_expressions' => [], 'data_preview' => ''],
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

    public function testGetFileExtensionReturnsJson(): void
    {
        $this->assertSame('json', $this->renderer->getFileExtension());
    }

    // ── renderFullIndex ────────────────────────────────────────────

    public function testRenderFullIndexReturnsValidJson(): void
    {
        $data = $this->dataBuilder->buildFullIndex($this->sampleMigrations());
        $output = $this->renderer->renderFullIndex($data);
        $decoded = json_decode($output, true);

        $this->assertNotNull($decoded);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function testRenderFullIndexContainsAllMigrations(): void
    {
        $data = $this->dataBuilder->buildFullIndex($this->sampleMigrations());
        $decoded = json_decode($this->renderer->renderFullIndex($data), true);

        $this->assertSame(3, $decoded['total_migrations']);
        $this->assertCount(3, $decoded['migrations']);
    }

    public function testRenderFullIndexContainsMetadata(): void
    {
        $data = $this->dataBuilder->buildFullIndex($this->sampleMigrations());
        $decoded = json_decode($this->renderer->renderFullIndex($data), true);

        $this->assertArrayHasKey('title', $decoded);
        $this->assertArrayHasKey('generated_at', $decoded);
        $this->assertArrayHasKey('total_migrations', $decoded);
    }

    public function testRenderFullIndexEmpty(): void
    {
        $data = $this->dataBuilder->buildFullIndex([]);
        $decoded = json_decode($this->renderer->renderFullIndex($data), true);

        $this->assertSame(0, $decoded['total_migrations']);
        $this->assertEmpty($decoded['migrations']);
    }

    // ── renderByTypeIndex ──────────────────────────────────────────

    public function testRenderByTypeGroupsMigrations(): void
    {
        $data = $this->dataBuilder->buildByTypeIndex($this->sampleMigrations());
        $decoded = json_decode($this->renderer->renderByTypeIndex($data), true);

        $this->assertArrayHasKey('groups', $decoded);
        $this->assertArrayHasKey('default', $decoded['groups']);
        $this->assertArrayHasKey('system', $decoded['groups']);
        $this->assertSame(2, $decoded['groups']['default']['count']);
    }

    public function testRenderByTypeEmptyGroups(): void
    {
        $data = $this->dataBuilder->buildByTypeIndex([]);
        $decoded = json_decode($this->renderer->renderByTypeIndex($data), true);

        $this->assertEmpty($decoded['groups']);
    }

    // ── renderByTableIndex ─────────────────────────────────────────

    public function testRenderByTableGroupsByTable(): void
    {
        $data = $this->dataBuilder->buildByTableIndex($this->sampleMigrations());
        $decoded = json_decode($this->renderer->renderByTableIndex($data), true);

        $this->assertArrayHasKey('tables', $decoded);
        $this->assertArrayHasKey('users', $decoded['tables']);
    }

    public function testRenderByTableEmpty(): void
    {
        $data = $this->dataBuilder->buildByTableIndex([]);
        $decoded = json_decode($this->renderer->renderByTableIndex($data), true);

        $this->assertEmpty($decoded['tables']);
    }

    // ── renderByOperationIndex ─────────────────────────────────────

    public function testRenderByOperationGroupsByOperation(): void
    {
        $data = $this->dataBuilder->buildByOperationIndex($this->sampleMigrations());
        $decoded = json_decode($this->renderer->renderByOperationIndex($data), true);

        $this->assertArrayHasKey('operations', $decoded);
        $this->assertArrayHasKey('CREATE', $decoded['operations']);
        $this->assertArrayHasKey('raw_sql', $decoded);
    }

    public function testRenderByOperationContainsDmlDetails(): void
    {
        $data = $this->dataBuilder->buildByOperationIndex($this->sampleMigrations());
        $decoded = json_decode($this->renderer->renderByOperationIndex($data), true);

        $dataMigrations = $decoded['operations']['DATA']['migrations'];
        $this->assertNotEmpty($dataMigrations);
        $this->assertNotEmpty($dataMigrations[0]['dml_operations']);
    }

    public function testRenderByOperationContainsRawSql(): void
    {
        $data = $this->dataBuilder->buildByOperationIndex($this->sampleMigrations());
        $decoded = json_decode($this->renderer->renderByOperationIndex($data), true);

        $this->assertSame(1, $decoded['raw_sql']['count']);
    }

    // ── renderStats ────────────────────────────────────────────────

    public function testRenderStatsReturnsValidJson(): void
    {
        $data = $this->dataBuilder->buildStats($this->sampleMigrations());
        $output = $this->renderer->renderStats($data);
        $decoded = json_decode($output, true);

        $this->assertNotNull($decoded);
        $this->assertSame(3, $decoded['total_migrations']);
    }

    public function testRenderStatsContainsAllKeys(): void
    {
        $data = $this->dataBuilder->buildStats($this->sampleMigrations());
        $decoded = json_decode($this->renderer->renderStats($data), true);

        $requiredKeys = ['generated_at', 'total_migrations', 'by_type', 'tables', 'complexity', 'data_modifications', 'raw_sql_count'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $decoded, "Missing key: {$key}");
        }
    }

    public function testRenderStatsEmpty(): void
    {
        $data = $this->dataBuilder->buildStats([]);
        $decoded = json_decode($this->renderer->renderStats($data), true);

        $this->assertSame(0, $decoded['total_migrations']);
    }
}
