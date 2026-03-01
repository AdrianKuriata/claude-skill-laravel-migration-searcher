<?php

namespace Tests\Unit\Renderers;

use DevSite\LaravelMigrationSearcher\Contracts\Services\TextSanitizer;
use DevSite\LaravelMigrationSearcher\Renderers\MarkdownMigrationFormatter;
use DevSite\LaravelMigrationSearcher\Services\HtmlSanitizer;
use Tests\TestCase;

class MarkdownMigrationFormatterTest extends TestCase
{
    protected MarkdownMigrationFormatter $formatter;
    protected TextSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer();
        $this->formatter = new MarkdownMigrationFormatter($this->sanitizer);
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

    public function testFormatterUsesSanitizerForHtmlInjection(): void
    {
        $migration = $this->sampleMigration();
        $migration['filename'] = '<script>alert(1)</script>.php';

        $content = $this->formatter->formatMigrationFull($migration);

        $this->assertStringNotContainsString('<script>', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
    }

    public function testFormatMigrationFullContainsAllSections(): void
    {
        $migration = $this->sampleMigration();
        $migration['foreign_keys'] = [['column' => 'role_id', 'references' => 'id', 'on_table' => 'roles']];
        $migration['indexes'] = [['type' => 'index', 'definition' => 'email']];
        $migration['dependencies'] = ['requires' => ['create_roles_table']];

        $content = $this->formatter->formatMigrationFull($migration);

        $this->assertStringContainsString('Tables:', $content);
        $this->assertStringContainsString('Columns:', $content);
        $this->assertStringContainsString('DDL Operations:', $content);
        $this->assertStringContainsString('Foreign Keys:', $content);
        $this->assertStringContainsString('Indexes:', $content);
        $this->assertStringContainsString('Dependencies:', $content);
    }

    public function testFormatMigrationFullSanitizesTableNames(): void
    {
        $migration = $this->sampleMigration();
        $migration['tables'] = ['<script>xss</script>' => ['operation' => 'CREATE', 'methods' => []]];

        $content = $this->formatter->formatMigrationFull($migration);

        $this->assertStringNotContainsString('<script>', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
    }

    public function testFormatMigrationFullWithTableDml(): void
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

        $content = $this->formatter->formatMigrationFull($migration);

        $this->assertStringContainsString('DML Operations:', $content);
        $this->assertStringContainsString('UPDATE', $content);
        $this->assertStringContainsString('on `users`', $content);
        $this->assertStringContainsString('WHERE:', $content);
        $this->assertStringContainsString('Columns: status', $content);
        $this->assertStringContainsString("Data: [&#039;status&#039; =&gt; &#039;inactive&#039;]", $content);
    }

    public function testFormatMigrationFullWithDbRaw(): void
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

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('Uses DB::raw', $content);
        $this->assertStringContainsString('CASE WHEN', $content);
    }

    public function testFormatMigrationFullWithDbRawLongExpression(): void
    {
        $migration = $this->sampleMigration();
        $longExpr = str_repeat('A', 150);
        $migration['dml_operations'] = [
            [
                'type' => 'UPDATE',
                'table' => 'orders',
                'where_conditions' => [],
                'columns_updated' => [],
                'has_db_raw' => true,
                'db_raw_expressions' => [$longExpr],
                'data_preview' => '',
            ],
        ];

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('...', $content);
    }

    public function testFormatMigrationFullWithEloquentDml(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            ['type' => 'INSERT', 'model' => 'User', 'method' => 'Eloquent::create', 'note' => 'Static call'],
        ];

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('User::Eloquent::create', $content);
        $this->assertStringContainsString('Static call', $content);
    }

    public function testFormatMigrationFullWithVariableDml(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            ['type' => 'UPDATE/INSERT', 'variable' => '$user', 'method' => 'Eloquent->save()', 'note' => 'Save', 'relation' => 'posts'],
        ];

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('$user->Eloquent-&gt;save()', $content);
        $this->assertStringContainsString('relation: posts', $content);
        $this->assertStringContainsString('Save', $content);
    }

    public function testFormatMigrationFullWithVariableDmlWithoutRelation(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            ['type' => 'UPDATE/INSERT', 'variable' => '$user', 'method' => 'save()'],
        ];

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('$user->save()', $content);
        $this->assertStringNotContainsString('relation:', $content);
    }

    public function testFormatMigrationFullWithLoopDml(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            ['type' => 'LOOP', 'method' => 'foreach', 'operations_in_loop' => ['save()'], 'note' => 'Loop ops'],
        ];

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('LOOP', $content);
        $this->assertStringContainsString('foreach', $content);
        $this->assertStringContainsString('Operations: save()', $content);
        $this->assertStringContainsString('Loop ops', $content);
    }

    public function testFormatMigrationFullWithRawSql(): void
    {
        $migration = $this->sampleMigration();
        $migration['raw_sql'] = [
            ['type' => 'statement', 'sql' => 'CREATE INDEX idx ON users (email)', 'operation' => 'CREATE'],
        ];

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('Raw SQL:', $content);
        $this->assertStringContainsString('```sql', $content);
    }

    public function testFormatMigrationFullWithForeignKeyWithoutOnTable(): void
    {
        $migration = $this->sampleMigration();
        $migration['foreign_keys'] = [['column' => 'user_id', 'references' => 'id', 'on_table' => '']];

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('Foreign Keys:', $content);
        $this->assertStringContainsString('`user_id`', $content);
    }

    public function testFormatMigrationFullWithColumnModifiers(): void
    {
        $migration = $this->sampleMigration();
        $migration['columns'] = ['email' => ['type' => 'string', 'modifiers' => ['unique', 'nullable']]];

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('`email` (string [unique, nullable])', $content);
    }

    public function testFormatMigrationFullWithEloquentDmlWithoutNote(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            ['type' => 'INSERT', 'model' => 'User', 'method' => 'create'],
        ];

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('via `User::create`', $content);
    }

    public function testFormatMigrationCompactContainsBasicInfo(): void
    {
        $content = $this->formatter->formatMigrationCompact($this->sampleMigration());

        $this->assertStringContainsString('create_users_table.php', $content);
        $this->assertStringContainsString('Tables:', $content);
        $this->assertStringContainsString('Complexity:', $content);
    }

    public function testFormatMigrationCompactShowsDataModificationWarning(): void
    {
        $migration = $this->sampleMigration();
        $migration['has_data_modifications'] = true;

        $content = $this->formatter->formatMigrationCompact($migration);
        $this->assertStringContainsString('Modifies data', $content);
    }

    public function testFormatMigrationCompactWithoutTables(): void
    {
        $migration = $this->sampleMigration();
        $migration['tables'] = [];

        $content = $this->formatter->formatMigrationCompact($migration);
        $this->assertStringContainsString('**Tables:** none', $content);
    }

    public function testFormatMigrationCompactWithoutColumns(): void
    {
        $migration = $this->sampleMigration();
        $migration['columns'] = [];

        $content = $this->formatter->formatMigrationCompact($migration);
        $this->assertStringNotContainsString('Columns:', $content);
    }

    public function testFormatDMLSummary(): void
    {
        $dml = [
            ['type' => 'UPDATE', 'table' => 'users'],
            ['type' => 'UPDATE', 'table' => 'orders'],
            ['type' => 'INSERT', 'table' => 'logs'],
        ];

        $summary = $this->formatter->formatDMLSummary($dml);
        $this->assertStringContainsString('UPDATE: 2', $summary);
        $this->assertStringContainsString('INSERT: 1', $summary);
    }

    public function testFormatMigrationFullUsesOnInsteadOfPolishNa(): void
    {
        $migration = $this->sampleMigration();
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

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('on `users`', $content);
        $this->assertStringNotContainsString(' na ', $content);
    }

    public function testFormatMigrationFullUsesViaInsteadOfPolishPrzez(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            ['type' => 'INSERT', 'model' => 'User', 'method' => 'create'],
        ];

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('via `User::create`', $content);
        $this->assertStringNotContainsString(' przez ', $content);
    }

    public function testFormatMigrationFullWithEmptyDependencies(): void
    {
        $migration = $this->sampleMigration();
        $migration['dependencies'] = ['requires' => []];

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('Dependencies:', $content);
        $this->assertStringNotContainsString('**requires:**', $content);
    }

    public function testFormatMigrationFullWithNonArrayDependencies(): void
    {
        $migration = $this->sampleMigration();
        $migration['dependencies'] = ['note' => 'some string value'];

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('Dependencies:', $content);
        $this->assertStringNotContainsString('**note:**', $content);
    }

    public function testFormatMigrationFullWithTableDmlWithoutWhereOrColumns(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            [
                'type' => 'INSERT',
                'table' => 'logs',
                'where_conditions' => [],
                'columns_updated' => [],
                'has_db_raw' => false,
                'db_raw_expressions' => [],
                'data_preview' => '',
            ],
        ];

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('on `logs`', $content);
        $this->assertStringNotContainsString('WHERE:', $content);
    }

    public function testFormatMigrationFullWithRawSqlWithoutOperation(): void
    {
        $migration = $this->sampleMigration();
        $migration['raw_sql'] = [
            ['type' => 'statement', 'sql' => 'SELECT 1'],
        ];

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('[unknown]', $content);
    }

    public function testFormatMigrationFullWithLoopDmlWithoutOperationsOrNote(): void
    {
        $migration = $this->sampleMigration();
        $migration['dml_operations'] = [
            ['type' => 'LOOP', 'method' => 'foreach'],
        ];

        $content = $this->formatter->formatMigrationFull($migration);
        $this->assertStringContainsString('LOOP', $content);
        $this->assertStringNotContainsString('  - Operations:', $content);
    }
}
