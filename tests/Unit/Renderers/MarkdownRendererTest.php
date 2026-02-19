<?php

namespace Tests\Unit\Renderers;

use DevSite\LaravelMigrationSearcher\Services\Renderers\MarkdownRenderer;
use Tests\TestCase;

class MarkdownRendererTest extends TestCase
{
    protected MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new MarkdownRenderer();
    }

    protected function sampleMigration(): array
    {
        return [
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
        ];
    }

    public function testRenderFullIndexContainsMigrationFilename(): void
    {
        $content = $this->renderer->renderFullIndex([$this->sampleMigration()]);
        $this->assertStringContainsString('2024_01_15_100000_create_users_table.php', $content);
    }

    public function testRenderFullIndexContainsHeader(): void
    {
        $content = $this->renderer->renderFullIndex([]);
        $this->assertStringContainsString('# Full Laravel Migrations Index', $content);
    }

    public function testRenderByTypeIndexContainsTypeHeaders(): void
    {
        $content = $this->renderer->renderByTypeIndex([]);
        $this->assertStringContainsString('System Migrations', $content);
        $this->assertStringContainsString('*No migrations of this type*', $content);
    }

    public function testRenderByTableIndexGroupsByTable(): void
    {
        $content = $this->renderer->renderByTableIndex([$this->sampleMigration()]);
        $this->assertStringContainsString('## Table: `users`', $content);
    }

    public function testRenderByOperationIndexGroupsByOperation(): void
    {
        $content = $this->renderer->renderByOperationIndex([$this->sampleMigration()]);
        $this->assertStringContainsString('Table Creation (CREATE)', $content);
    }

    public function testRenderStatsReturnsValidJson(): void
    {
        $statsJson = $this->renderer->renderStats([$this->sampleMigration()]);
        $stats = json_decode($statsJson, true);

        $this->assertIsArray($stats);
        $this->assertSame(1, $stats['total_migrations']);
        $this->assertArrayHasKey('complexity', $stats);
    }

    public function testFormatMigrationFullContainsAllSections(): void
    {
        $migration = $this->sampleMigration();
        $migration['foreign_keys'] = [['column' => 'role_id', 'references' => 'id', 'on_table' => 'roles']];
        $migration['indexes'] = [['type' => 'index', 'definition' => 'email']];
        $migration['dependencies'] = ['requires' => ['create_roles_table']];

        $content = $this->renderer->formatMigrationFull($migration);

        $this->assertStringContainsString('Tables:', $content);
        $this->assertStringContainsString('Columns:', $content);
        $this->assertStringContainsString('DDL Operations:', $content);
        $this->assertStringContainsString('Foreign Keys:', $content);
        $this->assertStringContainsString('Indexes:', $content);
        $this->assertStringContainsString('Dependencies:', $content);
    }

    public function testFormatMigrationCompactContainsBasicInfo(): void
    {
        $content = $this->renderer->formatMigrationCompact($this->sampleMigration());

        $this->assertStringContainsString('create_users_table.php', $content);
        $this->assertStringContainsString('Tables:', $content);
        $this->assertStringContainsString('Complexity:', $content);
    }

    public function testFormatMigrationCompactShowsDataModificationWarning(): void
    {
        $migration = $this->sampleMigration();
        $migration['has_data_modifications'] = true;

        $content = $this->renderer->formatMigrationCompact($migration);
        $this->assertStringContainsString('Modifies data', $content);
    }

    public function testFormatDMLSummary(): void
    {
        $dml = [
            ['type' => 'UPDATE', 'table' => 'users'],
            ['type' => 'UPDATE', 'table' => 'orders'],
            ['type' => 'INSERT', 'table' => 'logs'],
        ];

        $summary = $this->renderer->formatDMLSummary($dml);
        $this->assertStringContainsString('UPDATE: 2', $summary);
        $this->assertStringContainsString('INSERT: 1', $summary);
    }

    public function testRenderFullIndexWithDmlOperations(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            [
                'type' => 'UPDATE',
                'table' => 'users',
                'where_conditions' => ['active = false'],
                'columns_updated' => ['status'],
                'has_db_raw' => false,
                'db_raw_expressions' => [],
                'data_preview' => "['status' => 'inactive']",
            ],
        ];

        $content = $this->renderer->formatMigrationFull($migration);
        $this->assertStringContainsString('DML Operations:', $content);
        $this->assertStringContainsString('UPDATE', $content);
    }

    public function testRenderFullIndexWithRawSql(): void
    {
        $migration = $this->sampleMigration();
        $migration['raw_sql'] = [
            ['type' => 'statement', 'sql' => 'CREATE INDEX idx ON users (email)', 'operation' => 'CREATE'],
        ];

        $content = $this->renderer->formatMigrationFull($migration);
        $this->assertStringContainsString('Raw SQL:', $content);
        $this->assertStringContainsString('```sql', $content);
    }

    public function testRenderFullIndexWithEloquentDml(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            ['type' => 'INSERT', 'model' => 'User', 'method' => 'Eloquent::create', 'note' => 'Static call'],
        ];

        $content = $this->renderer->formatMigrationFull($migration);
        $this->assertStringContainsString('User::Eloquent::create', $content);
    }

    public function testRenderFullIndexWithVariableDml(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            ['type' => 'UPDATE/INSERT', 'variable' => '$user', 'method' => 'Eloquent->save()', 'note' => 'Save', 'relation' => 'posts'],
        ];

        $content = $this->renderer->formatMigrationFull($migration);
        $this->assertStringContainsString('$user->Eloquent->save()', $content);
        $this->assertStringContainsString('relation: posts', $content);
    }

    public function testRenderFullIndexWithLoopDml(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            ['type' => 'LOOP', 'method' => 'foreach', 'operations_in_loop' => ['save()'], 'note' => 'Loop ops'],
        ];

        $content = $this->renderer->formatMigrationFull($migration);
        $this->assertStringContainsString('LOOP', $content);
        $this->assertStringContainsString('foreach', $content);
    }

    public function testRenderFullIndexWithDbRaw(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            [
                'type' => 'UPDATE',
                'table' => 'orders',
                'where_conditions' => [],
                'columns_updated' => ['total'],
                'has_db_raw' => true,
                'db_raw_expressions' => ['CASE WHEN x THEN y END'],
                'data_preview' => '',
            ],
        ];

        $content = $this->renderer->formatMigrationFull($migration);
        $this->assertStringContainsString('Uses DB::raw', $content);
        $this->assertStringContainsString('CASE WHEN', $content);
    }

    public function testRenderByOperationWithDataDml(): void
    {
        $migration = $this->sampleMigration();
        $migration['tables'] = ['users' => ['operation' => 'DATA', 'methods' => []]];
        $migration['dml_operations'] = [
            [
                'type' => 'UPDATE',
                'table' => 'users',
                'where_conditions' => ['active = true'],
                'columns_updated' => ['status'],
                'has_db_raw' => false,
                'db_raw_expressions' => [],
                'data_preview' => '',
            ],
        ];

        $content = $this->renderer->renderByOperationIndex([$migration]);
        $this->assertStringContainsString('WHERE:', $content);
        $this->assertStringContainsString('columns: status', $content);
    }

    public function testRenderByOperationWithAlterColumns(): void
    {
        $migration = $this->sampleMigration();
        $migration['tables'] = ['users' => ['operation' => 'ALTER', 'methods' => []]];
        $migration['columns'] = ['bio' => ['type' => 'text', 'modifiers' => []]];

        $content = $this->renderer->renderByOperationIndex([$migration]);
        $this->assertStringContainsString('Affected columns', $content);
        $this->assertStringContainsString('bio', $content);
    }

    public function testRenderByOperationWithRawSql(): void
    {
        $migration = $this->sampleMigration();
        $migration['raw_sql'] = [
            ['type' => 'statement', 'sql' => 'SELECT 1', 'operation' => 'SELECT'],
        ];

        $content = $this->renderer->renderByOperationIndex([$migration]);
        $this->assertStringContainsString('## Raw SQL', $content);
    }

    public function testRenderStatsTableStatsTop50(): void
    {
        $migrations = [];
        for ($i = 0; $i < 60; $i++) {
            $migrations[] = [
                'filename' => "m{$i}.php",
                'type' => 'default',
                'tables' => ["table_{$i}" => ['operation' => 'CREATE', 'methods' => []]],
                'complexity' => 1,
                'has_data_modifications' => false,
                'raw_sql' => [],
            ];
        }

        $statsJson = $this->renderer->renderStats($migrations);
        $stats = json_decode($statsJson, true);
        $this->assertLessThanOrEqual(50, count($stats['tables']));
    }
}
