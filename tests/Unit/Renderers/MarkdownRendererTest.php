<?php

namespace Tests\Unit\Renderers;

use DevSite\LaravelMigrationSearcher\Renderers\MarkdownMigrationFormatter;
use DevSite\LaravelMigrationSearcher\Renderers\MarkdownRenderer;
use DevSite\LaravelMigrationSearcher\Services\HtmlSanitizer;
use DevSite\LaravelMigrationSearcher\Services\IndexDataBuilder;
use Tests\TestCase;

class MarkdownRendererTest extends TestCase
{
    protected MarkdownRenderer $renderer;
    protected IndexDataBuilder $dataBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $sanitizer = new HtmlSanitizer();
        $formatter = new MarkdownMigrationFormatter($sanitizer);
        $this->renderer = new MarkdownRenderer($formatter, $sanitizer);
        $this->dataBuilder = new IndexDataBuilder();
    }

    /** @param array<string, mixed> $overrides */
    protected function sampleDmlOperation(array $overrides = []): array
    {
        return array_merge([
            'type' => 'UPDATE',
            'table' => null,
            'model' => null,
            'variable' => null,
            'relation' => null,
            'method' => null,
            'note' => null,
            'data_preview' => null,
            'where_conditions' => [],
            'columns_updated' => [],
            'has_db_raw' => false,
            'db_raw_expressions' => [],
            'operations_in_loop' => [],
        ], $overrides);
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
            'dependencies' => [
                'requires' => [],
                'depends_on' => [],
                'foreign_keys' => [],
            ],
            'columns' => ['name' => ['type' => 'string', 'modifiers' => []]],
            'indexes' => [],
            'foreign_keys' => [],
            'methods_used' => ['id', 'string'],
            'has_data_modifications' => false,
            'complexity' => 2,
        ];
    }

    public function testGetFileExtension(): void
    {
        $this->assertSame('md', $this->renderer->getFileExtension());
    }

    public function testRenderFullIndexContainsMigrationFilename(): void
    {
        $data = $this->dataBuilder->buildFullIndex([$this->sampleMigration()]);
        $content = $this->renderer->renderFullIndex($data);
        $this->assertStringContainsString('2024_01_15_100000_create_users_table.php', $content);
    }

    public function testRenderFullIndexContainsHeader(): void
    {
        $data = $this->dataBuilder->buildFullIndex([]);
        $content = $this->renderer->renderFullIndex($data);
        $this->assertStringContainsString('# Full Laravel Migrations Index', $content);
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

        $data = $this->dataBuilder->buildFullIndex([$migration]);
        $content = $this->renderer->renderFullIndex($data);
        $this->assertStringContainsString('DML Operations:', $content);
        $this->assertStringContainsString('UPDATE', $content);
    }

    public function testRenderFullIndexWithRawSql(): void
    {
        $migration = $this->sampleMigration();
        $migration['raw_sql'] = [
            ['type' => 'statement', 'sql' => 'CREATE INDEX idx ON users (email)', 'operation' => 'CREATE'],
        ];

        $data = $this->dataBuilder->buildFullIndex([$migration]);
        $content = $this->renderer->renderFullIndex($data);
        $this->assertStringContainsString('Raw SQL:', $content);
        $this->assertStringContainsString('```sql', $content);
    }

    public function testRenderFullIndexWithEloquentDml(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            $this->sampleDmlOperation(['type' => 'INSERT', 'model' => 'User', 'method' => 'Eloquent::create', 'note' => 'Static call']),
        ];

        $data = $this->dataBuilder->buildFullIndex([$migration]);
        $content = $this->renderer->renderFullIndex($data);
        $this->assertStringContainsString('User::Eloquent::create', $content);
    }

    public function testRenderFullIndexWithVariableDml(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            $this->sampleDmlOperation(['type' => 'UPDATE/INSERT', 'variable' => '$user', 'method' => 'Eloquent->save()', 'note' => 'Save', 'relation' => 'posts']),
        ];

        $data = $this->dataBuilder->buildFullIndex([$migration]);
        $content = $this->renderer->renderFullIndex($data);
        $this->assertStringContainsString('$user->Eloquent-&gt;save()', $content);
        $this->assertStringContainsString('relation: posts', $content);
    }

    public function testRenderFullIndexWithLoopDml(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            $this->sampleDmlOperation(['type' => 'LOOP', 'method' => 'foreach', 'operations_in_loop' => ['save()'], 'note' => 'Loop ops']),
        ];

        $data = $this->dataBuilder->buildFullIndex([$migration]);
        $content = $this->renderer->renderFullIndex($data);
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

        $data = $this->dataBuilder->buildFullIndex([$migration]);
        $content = $this->renderer->renderFullIndex($data);
        $this->assertStringContainsString('Uses DB::raw', $content);
        $this->assertStringContainsString('CASE WHEN', $content);
    }

    public function testRenderFullIndexSanitizesTableNames(): void
    {
        $migration = $this->sampleMigration();
        $migration['tables'] = ['<script>xss</script>' => ['operation' => 'CREATE', 'methods' => []]];

        $data = $this->dataBuilder->buildFullIndex([$migration]);
        $content = $this->renderer->renderFullIndex($data);

        $this->assertStringNotContainsString('<script>', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
    }

    public function testRenderByTypeIndexContainsTypeHeaders(): void
    {
        $data = $this->dataBuilder->buildByTypeIndex([$this->sampleMigration()]);
        $content = $this->renderer->renderByTypeIndex($data);
        $this->assertStringContainsString('## default', $content);
    }

    public function testRenderByTypeIndexEmptyShowsNoMigrations(): void
    {
        $data = $this->dataBuilder->buildByTypeIndex([]);
        $content = $this->renderer->renderByTypeIndex($data);
        $this->assertStringContainsString('*No migrations found*', $content);
    }

    public function testRenderByTableIndexGroupsByTable(): void
    {
        $data = $this->dataBuilder->buildByTableIndex([$this->sampleMigration()]);
        $content = $this->renderer->renderByTableIndex($data);
        $this->assertStringContainsString('## Table: `users`', $content);
    }

    public function testRenderByOperationIndexGroupsByOperation(): void
    {
        $data = $this->dataBuilder->buildByOperationIndex([$this->sampleMigration()]);
        $content = $this->renderer->renderByOperationIndex($data);
        $this->assertStringContainsString('Table Creation (CREATE)', $content);
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

        $data = $this->dataBuilder->buildByOperationIndex([$migration]);
        $content = $this->renderer->renderByOperationIndex($data);
        $this->assertStringContainsString('WHERE:', $content);
        $this->assertStringContainsString('columns: status', $content);
    }

    public function testRenderByOperationWithAlterColumns(): void
    {
        $migration = $this->sampleMigration();
        $migration['tables'] = ['users' => ['operation' => 'ALTER', 'methods' => []]];
        $migration['columns'] = ['bio' => ['type' => 'text', 'modifiers' => []]];

        $data = $this->dataBuilder->buildByOperationIndex([$migration]);
        $content = $this->renderer->renderByOperationIndex($data);
        $this->assertStringContainsString('Affected columns', $content);
        $this->assertStringContainsString('bio', $content);
    }

    public function testRenderByOperationWithRawSql(): void
    {
        $migration = $this->sampleMigration();
        $migration['raw_sql'] = [
            ['type' => 'statement', 'sql' => 'SELECT 1', 'operation' => 'SELECT'],
        ];

        $data = $this->dataBuilder->buildByOperationIndex([$migration]);
        $content = $this->renderer->renderByOperationIndex($data);
        $this->assertStringContainsString('## Raw SQL', $content);
    }

    public function testRenderStatsReturnsMarkdown(): void
    {
        $data = $this->dataBuilder->buildStats([$this->sampleMigration()]);
        $content = $this->renderer->renderStats($data);

        $this->assertStringContainsString('# Migration Statistics', $content);
        $this->assertStringContainsString('**Total migrations:** 1', $content);
        $this->assertStringContainsString('## By Type', $content);
        $this->assertStringContainsString('**default:** 1', $content);
        $this->assertStringContainsString('## Complexity', $content);
        $this->assertStringContainsString('**Average:**', $content);
        $this->assertStringContainsString('## Data', $content);
        $this->assertStringContainsString('## Tables', $content);
        $this->assertStringContainsString('`users`', $content);
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

        $data = $this->dataBuilder->buildStats($migrations);
        $content = $this->renderer->renderStats($data);

        $this->assertStringContainsString('## Tables (top 50)', $content);
    }

    public function testRenderStatsWithEmptyData(): void
    {
        $data = $this->dataBuilder->buildStats([]);
        $content = $this->renderer->renderStats($data);

        $this->assertStringContainsString('**Total migrations:** 0', $content);
        $this->assertStringNotContainsString('## Tables', $content);
    }

    public function testRenderStatsSanitizesTypeNames(): void
    {
        $data = $this->dataBuilder->buildStats([$this->sampleMigration()]);
        $data['by_type'] = ['<script>alert(1)</script>' => 5];

        $content = $this->renderer->renderStats($data);

        $this->assertStringNotContainsString('<script>', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
    }

    public function testRenderStatsSanitizesTableNames(): void
    {
        $data = $this->dataBuilder->buildStats([$this->sampleMigration()]);
        $data['tables'] = [
            '<script>alert(1)</script>' => [
                'migrations_count' => 1,
                'operations' => ['CREATE' => 1],
            ],
        ];

        $content = $this->renderer->renderStats($data);

        $this->assertStringNotContainsString('<script>alert(1)</script>`', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
    }

    public function testRenderStatsSanitizesOperationNames(): void
    {
        $data = $this->dataBuilder->buildStats([$this->sampleMigration()]);
        $data['tables'] = [
            'users' => [
                'migrations_count' => 1,
                'operations' => ['<img src=x onerror=alert(1)>' => 1],
            ],
        ];

        $content = $this->renderer->renderStats($data);

        $this->assertStringNotContainsString('<img src=x', $content);
        $this->assertStringContainsString('&lt;img', $content);
    }

    public function testRenderByOperationUsesOnInsteadOfPolishNa(): void
    {
        $migration = $this->sampleMigration();
        $migration['tables'] = ['users' => ['operation' => 'DATA', 'methods' => []]];
        $migration['dml_operations'] = [
            [
                'type' => 'UPDATE',
                'table' => 'users',
                'where_conditions' => [],
                'columns_updated' => [],
                'has_db_raw' => false,
                'db_raw_expressions' => [],
                'data_preview' => '',
            ],
        ];

        $data = $this->dataBuilder->buildByOperationIndex([$migration]);
        $content = $this->renderer->renderByOperationIndex($data);
        $this->assertStringContainsString('on `users`', $content);
    }
}
