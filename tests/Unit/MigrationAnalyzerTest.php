<?php

namespace Tests\Unit;

use DevSite\LaravelMigrationSearcher\Services\MigrationAnalyzer;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MigrationAnalyzerTest extends TestCase
{
    protected MigrationAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new MigrationAnalyzer();
    }

    protected function analyzeFixture(string $filename): array
    {
        $filepath = $this->getFixturePath($filename);

        return $this->analyzer->analyze($filepath, 'default');
    }

    protected function analyzeContent(string $content, string $filename = '2024_01_01_000000_test.php'): array
    {
        $tmpDir = sys_get_temp_dir() . '/migration-analyzer-test-' . uniqid();
        mkdir($tmpDir, 0755, true);
        $filepath = $tmpDir . '/' . $filename;
        file_put_contents($filepath, $content);

        try {
            return $this->analyzer->analyze($filepath, 'default');
        } finally {
            @unlink($filepath);
            @rmdir($tmpDir);
        }
    }

    // ── Timestamp/Name ──────────────────────────────────────────────

    public function testExtractsTimestampFromStandardFilename(): void
    {
        $result = $this->analyzeFixture('2024_01_15_100000_create_users_table.php');

        $this->assertSame('2024_01_15_100000', $result['timestamp']);
    }

    public function testReturnsUnknownForNonStandardFilename(): void
    {
        $content = "<?php\n// empty migration\n";
        $result = $this->analyzeContent($content, 'custom_migration.php');

        $this->assertSame('unknown', $result['timestamp']);
    }

    public function testExtractsMigrationNameWithoutTimestamp(): void
    {
        $result = $this->analyzeFixture('2024_01_15_100000_create_users_table.php');

        $this->assertSame('create_users_table', $result['name']);
    }

    // ── Table Detection ─────────────────────────────────────────────

    public function testDetectsSchemaCreate(): void
    {
        $result = $this->analyzeFixture('2024_01_15_100000_create_users_table.php');

        $this->assertArrayHasKey('users', $result['tables']);
        $this->assertSame('CREATE', $result['tables']['users']['operation']);
    }

    public function testDetectsSchemaTable(): void
    {
        $result = $this->analyzeFixture('2024_02_20_100000_alter_users_add_avatar.php');

        $this->assertArrayHasKey('users', $result['tables']);
        $this->assertSame('ALTER', $result['tables']['users']['operation']);
    }

    public function testDetectsSchemaDropIfExists(): void
    {
        $result = $this->analyzeFixture('2024_03_10_100000_drop_legacy_table.php');

        $this->assertArrayHasKey('legacy_settings', $result['tables']);
        $this->assertSame('DROP', $result['tables']['legacy_settings']['operation']);
    }

    public function testDetectsSchemaRename(): void
    {
        $result = $this->analyzeFixture('2024_04_01_100000_rename_orders.php');

        $this->assertArrayHasKey('orders', $result['tables']);
        $this->assertSame('RENAME', $result['tables']['orders']['operation']);
    }

    public function testDetectsDbTable(): void
    {
        $result = $this->analyzeFixture('2024_05_15_100000_data_migration.php');

        $this->assertArrayHasKey('users', $result['tables']);
        $this->assertSame('DATA', $result['tables']['users']['operation']);
    }

    public function testMultipleTablesInOneMigration(): void
    {
        $result = $this->analyzeFixture('2024_07_01_100000_complex_migration.php');

        $this->assertArrayHasKey('categories', $result['tables']);
        $this->assertArrayHasKey('products', $result['tables']);
        $this->assertArrayHasKey('product_variants', $result['tables']);
    }

    public function testSchemaCreatePrecedenceOverDbTable(): void
    {
        $content = <<<'PHP'
        <?php
        Schema::create('items', function ($table) {
            $table->id();
        });
        DB::table('items')->insert(['name' => 'test']);
        PHP;

        $result = $this->analyzeContent($content);

        $this->assertSame('CREATE', $result['tables']['items']['operation']);
    }

    // ── DDL Operations ──────────────────────────────────────────────

    public function testExtractsBlueprintColumnMethods(): void
    {
        $result = $this->analyzeFixture('2024_01_15_100000_create_users_table.php');

        $categories = array_column($result['ddl_operations'], 'category');
        $this->assertContains('column_create', $categories);
    }

    public function testExtractsIndexOperations(): void
    {
        $result = $this->analyzeFixture('2024_07_01_100000_complex_migration.php');

        $indexOps = array_filter($result['ddl_operations'], fn($op) => $op['category'] === 'index');
        $this->assertNotEmpty($indexOps);
    }

    public function testExtractsForeignKeyOperations(): void
    {
        $result = $this->analyzeFixture('2024_07_01_100000_complex_migration.php');

        $fkOps = array_filter($result['ddl_operations'], fn($op) => $op['category'] === 'foreign_key');
        $this->assertNotEmpty($fkOps);
    }

    public function testExtractsDropOperations(): void
    {
        $content = <<<'PHP'
        <?php
        Schema::table('users', function ($table) {
            $table->dropColumn('avatar');
            $table->dropIndex('users_email_index');
            $table->dropForeign('users_role_id_foreign');
        });
        PHP;

        $result = $this->analyzeContent($content);

        $categories = array_column($result['ddl_operations'], 'category');
        $this->assertContains('column_modify', $categories);
        $this->assertContains('index_drop', $categories);
        $this->assertContains('foreign_key_drop', $categories);
    }

    // ── DML Operations ──────────────────────────────────────────────

    public function testDetectsDbTableUpdate(): void
    {
        $result = $this->analyzeFixture('2024_05_15_100000_data_migration.php');

        $updates = array_filter($result['dml_operations'], fn($op) => $op['type'] === 'UPDATE');
        $this->assertNotEmpty($updates);

        $update = array_values($updates)[0];
        $this->assertSame('users', $update['table']);
        $this->assertNotEmpty($update['where_conditions']);
        $this->assertNotEmpty($update['columns_updated']);
    }

    public function testDetectsDbTableInsert(): void
    {
        $content = <<<'PHP'
        <?php
        DB::table('settings')->whereNull('deleted_at')->insert([
            'key' => 'app_version',
            'value' => '2.0',
        ]);
        PHP;

        $result = $this->analyzeContent($content);

        $inserts = array_filter($result['dml_operations'], fn($op) => $op['type'] === 'INSERT');
        $this->assertNotEmpty($inserts);
    }

    public function testDetectsDbTableDelete(): void
    {
        $result = $this->analyzeFixture('2024_05_15_100000_data_migration.php');

        $deletes = array_filter($result['dml_operations'], fn($op) => $op['type'] === 'DELETE');
        $this->assertNotEmpty($deletes);
    }

    public function testDetectsEloquentCreate(): void
    {
        $result = $this->analyzeFixture('2024_09_01_100000_eloquent_ops.php');

        $eloquentInserts = array_filter(
            $result['dml_operations'],
            fn($op) => $op['type'] === 'INSERT' && isset($op['method']) && str_contains($op['method'], 'Eloquent::create')
        );
        $this->assertNotEmpty($eloquentInserts);
    }

    public function testDetectsEloquentSave(): void
    {
        $result = $this->analyzeFixture('2024_09_01_100000_eloquent_ops.php');

        $saves = array_filter(
            $result['dml_operations'],
            fn($op) => $op['type'] === 'UPDATE/INSERT' && isset($op['method']) && str_contains($op['method'], 'save')
        );
        $this->assertNotEmpty($saves);
    }

    public function testDetectsEloquentRelationCreate(): void
    {
        $result = $this->analyzeFixture('2024_09_01_100000_eloquent_ops.php');

        $relationCreates = array_filter(
            $result['dml_operations'],
            fn($op) => isset($op['relation'])
        );
        $this->assertNotEmpty($relationCreates);
    }

    public function testDetectsEloquentDelete(): void
    {
        $result = $this->analyzeFixture('2024_09_01_100000_eloquent_ops.php');

        $deletes = array_filter(
            $result['dml_operations'],
            fn($op) => $op['type'] === 'DELETE' && isset($op['method']) && str_contains($op['method'], 'delete')
        );
        $this->assertNotEmpty($deletes);
    }

    public function testDetectsLoopOperations(): void
    {
        $result = $this->analyzeFixture('2024_09_01_100000_eloquent_ops.php');

        $loops = array_filter(
            $result['dml_operations'],
            fn($op) => $op['type'] === 'LOOP'
        );
        $this->assertNotEmpty($loops);

        $loop = array_values($loops)[0];
        $this->assertSame('foreach', $loop['method']);
        $this->assertNotEmpty($loop['operations_in_loop']);
    }

    public function testDetectsDbRawInUpdate(): void
    {
        $result = $this->analyzeFixture('2024_06_01_100000_raw_sql_migration.php');

        $updatesWithRaw = array_filter(
            $result['dml_operations'],
            fn($op) => $op['type'] === 'UPDATE' && !empty($op['has_db_raw'])
        );
        $this->assertNotEmpty($updatesWithRaw);

        $update = array_values($updatesWithRaw)[0];
        $this->assertTrue($update['has_db_raw']);
        $this->assertNotEmpty($update['db_raw_expressions']);
    }

    // ── Raw SQL ─────────────────────────────────────────────────────

    public function testDetectsDbStatement(): void
    {
        $result = $this->analyzeFixture('2024_06_01_100000_raw_sql_migration.php');

        $statements = array_filter($result['raw_sql'], fn($s) => $s['type'] === 'statement');
        $this->assertNotEmpty($statements);
    }

    public function testDetectsDbUnprepared(): void
    {
        $result = $this->analyzeFixture('2024_06_01_100000_raw_sql_migration.php');

        $unprepared = array_filter($result['raw_sql'], fn($s) => $s['type'] === 'unprepared');
        $this->assertNotEmpty($unprepared);
    }

    public function testDetectsHeredocSql(): void
    {
        $result = $this->analyzeFixture('2024_06_01_100000_raw_sql_migration.php');

        $heredocs = array_filter($result['raw_sql'], fn($s) => $s['type'] === 'heredoc');
        $this->assertNotEmpty($heredocs);
    }

    // ── Columns/Modifiers ───────────────────────────────────────────

    public function testExtractsColumnsWithTypes(): void
    {
        $result = $this->analyzeFixture('2024_01_15_100000_create_users_table.php');

        $this->assertArrayHasKey('name', $result['columns']);
        $this->assertSame('string', $result['columns']['name']['type']);

        $this->assertArrayHasKey('email', $result['columns']);
        $this->assertSame('string', $result['columns']['email']['type']);

        $this->assertArrayHasKey('is_active', $result['columns']);
        $this->assertSame('boolean', $result['columns']['is_active']['type']);
    }

    public function testExtractsColumnModifiers(): void
    {
        $result = $this->analyzeFixture('2024_02_20_100000_alter_users_add_avatar.php');

        $this->assertArrayHasKey('avatar', $result['columns']);
        $this->assertSame('string', $result['columns']['avatar']['type']);

        $this->assertArrayHasKey('bio', $result['columns']);
        $this->assertSame('text', $result['columns']['bio']['type']);

        $this->assertNotEmpty($result['methods_used']);
    }

    // ── Complexity ──────────────────────────────────────────────────

    public function testMinimumComplexityIsOne(): void
    {
        $result = $this->analyzeFixture('2024_08_01_100000_empty_migration.php');

        $this->assertSame(1, $result['complexity']);
    }

    public function testComplexityCapAtTen(): void
    {
        $result = $this->analyzeFixture('2024_07_01_100000_complex_migration.php');

        $this->assertLessThanOrEqual(10, $result['complexity']);
        $this->assertGreaterThanOrEqual(1, $result['complexity']);
    }

    public function testComplexityScoringFormula(): void
    {
        $analyzer = new MigrationAnalyzer();

        $simple = $analyzer->analyze(
            $this->getFixturePath('2024_03_10_100000_drop_legacy_table.php'),
            'default'
        );

        $complex = $analyzer->analyze(
            $this->getFixturePath('2024_07_01_100000_complex_migration.php'),
            'default'
        );

        $afterComplex = $analyzer->analyze(
            $this->getFixturePath('2024_08_01_100000_empty_migration.php'),
            'default'
        );

        $this->assertSame(1, $afterComplex['complexity'],
            'BUG FIXED: Empty migration always gets complexity 1 regardless of call order.'
        );

        $this->assertGreaterThan($simple['complexity'], $complex['complexity'],
            'Complex migration should have higher complexity than simple one.'
        );
    }

    // ── Security/Edge Cases ─────────────────────────────────────────

    public function testDoesNotExecuteMigrationCode(): void
    {
        $content = <<<'PHP'
        <?php
        // This migration contains dangerous code that MUST NOT be executed
        system('echo PWNED');
        exec('whoami');
        Schema::create('safe_table', function ($table) {
            $table->id();
        });
        PHP;

        $result = $this->analyzeContent($content);

        $this->assertArrayHasKey('safe_table', $result['tables']);
        $this->assertSame('CREATE', $result['tables']['safe_table']['operation']);
    }

    public function testHandlesEmptyContent(): void
    {
        $content = "<?php\n";
        $result = $this->analyzeContent($content);

        $this->assertEmpty($result['tables']);
        $this->assertEmpty($result['ddl_operations']);
        $this->assertEmpty($result['dml_operations']);
        $this->assertEmpty($result['raw_sql']);
        $this->assertSame(1, $result['complexity']);
    }

    public function testHandlesSpecialCharsInTableNames(): void
    {
        $content = <<<'PHP'
        <?php
        Schema::create('user-logs', function ($table) {
            $table->id();
        });
        Schema::create('app_v2_settings', function ($table) {
            $table->id();
        });
        PHP;

        $result = $this->analyzeContent($content);

        $this->assertArrayHasKey('user-logs', $result['tables']);
        $this->assertArrayHasKey('app_v2_settings', $result['tables']);
    }

    public function testHasDataModificationsAllConditions(): void
    {
        $withDml = $this->analyzeFixture('2024_05_15_100000_data_migration.php');
        $this->assertTrue($withDml['has_data_modifications']);

        $withRawSql = $this->analyzeFixture('2024_06_01_100000_raw_sql_migration.php');
        $this->assertTrue($withRawSql['has_data_modifications']);

        $content = "<?php\nDB::table('users')->get();\n";
        $withDbTable = $this->analyzeContent($content);
        $this->assertTrue($withDbTable['has_data_modifications']);

        $content = "<?php\n\App\Models\User::create(['name' => 'test']);\n";
        $withCreate = $this->analyzeContent($content);
        $this->assertTrue($withCreate['has_data_modifications']);

        $content = "<?php\n\App\Models\User::update(['name' => 'test']);\n";
        $withUpdate = $this->analyzeContent($content);
        $this->assertTrue($withUpdate['has_data_modifications']);

        $noData = $this->analyzeFixture('2024_08_01_100000_empty_migration.php');
        $this->assertFalse($noData['has_data_modifications']);
    }

    // ── Foreign Keys ────────────────────────────────────────────────

    public function testExtractsForeignKeys(): void
    {
        $result = $this->analyzeFixture('2024_01_15_100000_create_users_table.php');

        $this->assertNotEmpty($result['foreign_keys']);

        $fk = collect($result['foreign_keys'])->firstWhere('column', 'department_id');
        $this->assertNotNull($fk);
        $this->assertSame('id', $fk['references']);
        $this->assertSame('departments', $fk['on_table']);
    }

    // ── Result Structure ────────────────────────────────────────────

    public function testAnalyzeReturnsAllRequiredKeys(): void
    {
        $result = $this->analyzeFixture('2024_01_15_100000_create_users_table.php');

        $requiredKeys = [
            'filename', 'filepath', 'relative_path', 'type', 'timestamp',
            'name', 'tables', 'ddl_operations', 'dml_operations', 'raw_sql',
            'dependencies', 'columns', 'indexes', 'foreign_keys',
            'methods_used', 'has_data_modifications', 'complexity',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }
    }

    public function testAnalyzeSetsTypeFromParameter(): void
    {
        $filepath = $this->getFixturePath('2024_08_01_100000_empty_migration.php');
        $result = $this->analyzer->analyze($filepath, 'custom_type');

        $this->assertSame('custom_type', $result['type']);
    }

    // ── extractWhereConditions ──────────────────────────────────────

    public function testExtractsWhereInCondition(): void
    {
        $content = <<<'PHP'
        <?php
        DB::table('users')->whereIn('status', ['active', 'pending'])->update(['verified' => true]);
        PHP;

        $result = $this->analyzeContent($content);

        $updates = array_filter($result['dml_operations'], fn($op) => $op['type'] === 'UPDATE');
        $update = array_values($updates)[0];
        $this->assertContains('status IN (...)', $update['where_conditions']);
    }

    public function testExtractsWhereNotInCondition(): void
    {
        $content = <<<'PHP'
        <?php
        DB::table('users')->whereNotIn('role', ['admin', 'super'])->update(['active' => false]);
        PHP;

        $result = $this->analyzeContent($content);

        $updates = array_filter($result['dml_operations'], fn($op) => $op['type'] === 'UPDATE');
        $update = array_values($updates)[0];
        $this->assertContains('role NOT IN (...)', $update['where_conditions']);
    }

    public function testExtractsWhereNullCondition(): void
    {
        $content = <<<'PHP'
        <?php
        DB::table('users')->whereNull('deleted_at')->update(['active' => true]);
        PHP;

        $result = $this->analyzeContent($content);

        $updates = array_filter($result['dml_operations'], fn($op) => $op['type'] === 'UPDATE');
        $update = array_values($updates)[0];
        $this->assertContains('deleted_at IS NULL', $update['where_conditions']);
    }

    public function testExtractsWhereNotNullCondition(): void
    {
        $content = <<<'PHP'
        <?php
        DB::table('users')->whereNotNull('email_verified_at')->update(['verified' => true]);
        PHP;

        $result = $this->analyzeContent($content);

        $updates = array_filter($result['dml_operations'], fn($op) => $op['type'] === 'UPDATE');
        $update = array_values($updates)[0];
        $this->assertContains('email_verified_at IS NOT NULL', $update['where_conditions']);
    }

    public function testExtractsWhereBetweenCondition(): void
    {
        $content = <<<'PHP'
        <?php
        DB::table('orders')->whereBetween('created_at', ['2024-01-01', '2024-12-31'])->update(['archived' => true]);
        PHP;

        $result = $this->analyzeContent($content);

        $updates = array_filter($result['dml_operations'], fn($op) => $op['type'] === 'UPDATE');
        $update = array_values($updates)[0];
        $this->assertContains('created_at BETWEEN (...)', $update['where_conditions']);
    }

    public function testExtractsWhereHasCondition(): void
    {
        $content = <<<'PHP'
        <?php
        DB::table('users')->whereHas('posts')->update(['has_posts' => true]);
        PHP;

        $result = $this->analyzeContent($content);

        $updates = array_filter($result['dml_operations'], fn($op) => $op['type'] === 'UPDATE');
        $update = array_values($updates)[0];
        $this->assertContains('HAS posts', $update['where_conditions']);
    }

    public function testExtractsWhereDoesntHaveCondition(): void
    {
        $content = <<<'PHP'
        <?php
        DB::table('users')->whereDoesntHave('orders')->update(['inactive' => true]);
        PHP;

        $result = $this->analyzeContent($content);

        $updates = array_filter($result['dml_operations'], fn($op) => $op['type'] === 'UPDATE');
        $update = array_values($updates)[0];
        $this->assertContains("DOESN'T HAVE orders", $update['where_conditions']);
    }

    public function testExtractsOrWhereCondition(): void
    {
        $content = <<<'PHP'
        <?php
        DB::table('users')->where('active', false)->orWhere('banned', true)->update(['status' => 'blocked']);
        PHP;

        $result = $this->analyzeContent($content);

        $updates = array_filter($result['dml_operations'], fn($op) => $op['type'] === 'UPDATE');
        $update = array_values($updates)[0];
        $this->assertContains('OR banned = true', $update['where_conditions']);
    }

    public function testExtractsOrWhereWithOperator(): void
    {
        $content = <<<'PHP'
        <?php
        DB::table('users')->where('age', '>', 18)->orWhere('age', '<', 5)->update(['flagged' => true]);
        PHP;

        $result = $this->analyzeContent($content);

        $updates = array_filter($result['dml_operations'], fn($op) => $op['type'] === 'UPDATE');
        $update = array_values($updates)[0];
        $this->assertContains('OR age < 5', $update['where_conditions']);
    }

    public function testTruncatesLongOrWhereValues(): void
    {
        $longValue = str_repeat('y', 60);
        $content = "<?php\nDB::table('users')->where('name', 'x')->orWhere('email', '{$longValue}')->update(['flag' => true]);\n";

        $result = $this->analyzeContent($content);

        $updates = array_filter($result['dml_operations'], fn($op) => $op['type'] === 'UPDATE');
        $update = array_values($updates)[0];
        $orConditions = array_filter($update['where_conditions'], fn($c) => str_starts_with($c, 'OR '));
        $orCondition = array_values($orConditions)[0];
        $this->assertStringContainsString('...', $orCondition);
    }

    public function testTruncatesLongWhereValues(): void
    {
        $longValue = str_repeat('x', 60);
        $content = <<<PHP
        <?php
        DB::table('users')->where('name', '{$longValue}')->update(['flag' => true]);
        PHP;

        $result = $this->analyzeContent($content);

        $updates = array_filter($result['dml_operations'], fn($op) => $op['type'] === 'UPDATE');
        $update = array_values($updates)[0];
        $condition = $update['where_conditions'][0];
        $this->assertStringContainsString('...', $condition);
        $this->assertLessThanOrEqual(70, strlen($condition));
    }

    // ── extractDependencies ─────────────────────────────────────────

    public function testExtractsRequiresAnnotation(): void
    {
        $content = <<<'PHP'
        <?php
        // @requires create_users_table
        Schema::table('profiles', function ($table) {
            $table->foreignId('user_id');
        });
        PHP;

        $result = $this->analyzeContent($content);

        $this->assertArrayHasKey('requires', $result['dependencies']);
        $this->assertContains('create_users_table', $result['dependencies']['requires']);
    }

    public function testExtractsDependsOnAnnotation(): void
    {
        $content = <<<'PHP'
        <?php
        // @depends on create_roles_table
        Schema::table('users', function ($table) {
            $table->foreignId('role_id');
        });
        PHP;

        $result = $this->analyzeContent($content);

        $this->assertArrayHasKey('depends_on', $result['dependencies']);
        $this->assertContains('create_roles_table', $result['dependencies']['depends_on']);
    }

    // ── extractIndexes ──────────────────────────────────────────────

    public function testExtractsStandaloneUniqueIndex(): void
    {
        $content = <<<'PHP'
        <?php
        Schema::table('users', function ($table) {
            $table->unique(['email', 'tenant_id']);
        });
        PHP;

        $result = $this->analyzeContent($content);

        $uniqueIndexes = array_filter($result['indexes'], fn($idx) => $idx['type'] === 'unique');
        $this->assertNotEmpty($uniqueIndexes);
    }

    // ── Loop operations ───────────────────────────────────────────

    public function testDetectsCreateAndUpdateInLoop(): void
    {
        $content = <<<'PHP'
        <?php
        foreach ($items as $item) {
            $item->update(['status' => 'done']);
            $item->children()->create(['name' => 'child']);
        }
        PHP;

        $result = $this->analyzeContent($content);

        $loops = array_filter($result['dml_operations'], fn($op) => $op['type'] === 'LOOP');
        $this->assertNotEmpty($loops);

        $loop = array_values($loops)[0];
        $this->assertContains('create()', $loop['operations_in_loop']);
        $this->assertContains('update()', $loop['operations_in_loop']);
    }

    // ── hasDataModifications ────────────────────────────────────────

    public function testHasDataModificationsInsertCondition(): void
    {
        $content = "<?php\n\App\Models\User::insert(['name' => 'test']);\n";
        $result = $this->analyzeContent($content);
        $this->assertTrue($result['has_data_modifications']);
    }

    // ── File Size Limit ────────────────────────────────────────────

    public function testThrowsExceptionWhenFileExceedsMaxSize(): void
    {
        $this->app['config']->set('migration-searcher.max_file_size', 50);

        $content = str_repeat('x', 100);
        $tmpDir = sys_get_temp_dir() . '/migration-size-test-' . uniqid();
        mkdir($tmpDir, 0755, true);
        $filepath = $tmpDir . '/2024_01_01_000000_large.php';
        file_put_contents($filepath, $content);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('File exceeds maximum allowed size');
            $this->analyzer->analyze($filepath, 'default');
        } finally {
            @unlink($filepath);
            @rmdir($tmpDir);
        }
    }

    public function testAllowsFileWithinMaxSize(): void
    {
        $this->app['config']->set('migration-searcher.max_file_size', 5242880);

        $result = $this->analyzeFixture('2024_08_01_100000_empty_migration.php');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('filename', $result);
    }
}
