<?php

namespace Tests\Unit\Parsers;

use DevSite\LaravelMigrationSearcher\Services\Parsers\DmlParser;
use PHPUnit\Framework\TestCase;

class DmlParserTest extends TestCase
{
    protected DmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DmlParser();
    }

    public function testParseDelegatesToExtractDMLOperations(): void
    {
        $content = '<?php' . "\n";
        $this->assertSame(
            $this->parser->extractDMLOperations($content),
            $this->parser->parse($content)
        );
    }

    public function testExtractsDbTableUpdate(): void
    {
        $content = "DB::table('users')->where('active', false)->update(['status' => 'inactive']);";
        $ops = $this->parser->extractDMLOperations($content);

        $updates = array_filter($ops, fn($op) => $op['type'] === 'UPDATE');
        $this->assertNotEmpty($updates);

        $update = array_values($updates)[0];
        $this->assertSame('users', $update['table']);
    }

    public function testExtractsDbTableInsert(): void
    {
        $content = "DB::table('settings')->whereNull('deleted_at')->insert(['key' => 'v']);";
        $ops = $this->parser->extractDMLOperations($content);

        $inserts = array_filter($ops, fn($op) => $op['type'] === 'INSERT');
        $this->assertNotEmpty($inserts);
    }

    public function testExtractsDbTableDelete(): void
    {
        $content = "DB::table('users')->where('banned', true)->delete();";
        $ops = $this->parser->extractDMLOperations($content);

        $deletes = array_filter($ops, fn($op) => $op['type'] === 'DELETE');
        $this->assertNotEmpty($deletes);
    }

    public function testExtractsEloquentCreate(): void
    {
        $content = '\App\Models\User::create([\'name\' => \'test\']);';
        $ops = $this->parser->extractDMLOperations($content);

        $creates = array_filter($ops, fn($op) => $op['type'] === 'INSERT' && isset($op['model']));
        $this->assertNotEmpty($creates);
        $this->assertSame('User', array_values($creates)[0]['model']);
    }

    public function testExtractsEloquentSave(): void
    {
        $content = '$user->save();';
        $ops = $this->parser->extractDMLOperations($content);

        $saves = array_filter($ops, fn($op) => $op['type'] === 'UPDATE/INSERT');
        $this->assertNotEmpty($saves);
        $this->assertSame('$user', array_values($saves)[0]['variable']);
    }

    public function testExtractsEloquentRelationCreate(): void
    {
        $content = '$user->posts()->create([\'title\' => \'test\']);';
        $ops = $this->parser->extractDMLOperations($content);

        $creates = array_filter($ops, fn($op) => isset($op['relation']));
        $this->assertNotEmpty($creates);
        $this->assertSame('posts', array_values($creates)[0]['relation']);
    }

    public function testExtractsEloquentDelete(): void
    {
        $content = '$user->delete();';
        $ops = $this->parser->extractDMLOperations($content);

        $deletes = array_filter($ops, fn($op) => $op['type'] === 'DELETE' && isset($op['variable']));
        $this->assertNotEmpty($deletes);
    }

    public function testExtractsLoopOperations(): void
    {
        $content = 'foreach ($items as $item) { $item->save(); }';
        $ops = $this->parser->extractDMLOperations($content);

        $loops = array_filter($ops, fn($op) => $op['type'] === 'LOOP');
        $this->assertNotEmpty($loops);
        $this->assertSame('foreach', array_values($loops)[0]['method']);
    }

    public function testExtractsWhereConditions(): void
    {
        $chain = "->where('active', true)->whereIn('status', ['a','b'])->whereNull('deleted_at')";
        $conditions = $this->parser->extractWhereConditions($chain);

        $this->assertContains('active = true', $conditions);
        $this->assertContains('status IN (...)', $conditions);
        $this->assertContains('deleted_at IS NULL', $conditions);
    }

    public function testExtractsWhereNotNullCondition(): void
    {
        $chain = "->whereNotNull('email')";
        $conditions = $this->parser->extractWhereConditions($chain);
        $this->assertContains('email IS NOT NULL', $conditions);
    }

    public function testExtractsWhereNotInCondition(): void
    {
        $chain = "->whereNotIn('role', ['admin'])";
        $conditions = $this->parser->extractWhereConditions($chain);
        $this->assertContains('role NOT IN (...)', $conditions);
    }

    public function testExtractsWhereBetweenCondition(): void
    {
        $chain = "->whereBetween('age', [18, 65])";
        $conditions = $this->parser->extractWhereConditions($chain);
        $this->assertContains('age BETWEEN (...)', $conditions);
    }

    public function testExtractsWhereHasCondition(): void
    {
        $chain = "->whereHas('posts')";
        $conditions = $this->parser->extractWhereConditions($chain);
        $this->assertContains('HAS posts', $conditions);
    }

    public function testExtractsWhereDoesntHaveCondition(): void
    {
        $chain = "->whereDoesntHave('orders')";
        $conditions = $this->parser->extractWhereConditions($chain);
        $this->assertContains("DOESN'T HAVE orders", $conditions);
    }

    public function testExtractsOrWhereCondition(): void
    {
        $chain = "->orWhere('banned', true)";
        $conditions = $this->parser->extractWhereConditions($chain);
        $this->assertContains('OR banned = true', $conditions);
    }

    public function testTruncatesLongWhereValues(): void
    {
        $longValue = str_repeat('x', 60);
        $chain = "->where('name', '{$longValue}')";
        $conditions = $this->parser->extractWhereConditions($chain);
        $this->assertStringContainsString('...', $conditions[0]);
    }

    public function testTruncatesLongOrWhereValues(): void
    {
        $longValue = str_repeat('y', 60);
        $chain = "->orWhere('email', '{$longValue}')";
        $conditions = $this->parser->extractWhereConditions($chain);
        $this->assertStringContainsString('...', $conditions[0]);
    }

    public function testExtractsColumnsFromArray(): void
    {
        $array = "['name' => 'test', 'email' => 'foo@bar.com']";
        $columns = $this->parser->extractColumnsFromArray($array);

        $this->assertContains('name', $columns);
        $this->assertContains('email', $columns);
    }

    public function testCleanupDataPreview(): void
    {
        $data = "   ['name' => 'test']   ";
        $this->assertSame("['name' => 'test']", $this->parser->cleanupDataPreview($data));
    }

    public function testCleanupDataPreviewTruncatesLongData(): void
    {
        $data = str_repeat('a', 200);
        $result = $this->parser->cleanupDataPreview($data, 150);
        $this->assertStringEndsWith('...', $result);
        $this->assertLessThanOrEqual(153, strlen($result));
    }

    public function testHasDataModificationsWithDml(): void
    {
        $content = "DB::table('users')->where('x', 1)->update(['a' => 1]);";
        $this->assertTrue($this->parser->hasDataModifications($content));
    }

    public function testHasDataModificationsWithDbTable(): void
    {
        $content = "DB::table('users')->get();";
        $this->assertTrue($this->parser->hasDataModifications($content));
    }

    public function testHasDataModificationsWithCreate(): void
    {
        $content = "User::create(['name' => 'test']);";
        $this->assertTrue($this->parser->hasDataModifications($content));
    }

    public function testHasDataModificationsWithUpdate(): void
    {
        $content = "User::update(['name' => 'test']);";
        $this->assertTrue($this->parser->hasDataModifications($content));
    }

    public function testHasDataModificationsWithInsert(): void
    {
        $content = "User::insert(['name' => 'test']);";
        $this->assertTrue($this->parser->hasDataModifications($content));
    }

    public function testHasNoDataModifications(): void
    {
        $content = "<?php\n\$table->id();\n\$table->string('name');\n";
        $this->assertFalse($this->parser->hasDataModifications($content));
    }

    public function testExtractsDbRawInUpdate(): void
    {
        $content = "DB::table('orders')->where('status', 'pending')->update(['total' => DB::raw('total * 0.9')]);";
        $ops = $this->parser->extractDMLOperations($content);

        $updates = array_filter($ops, fn($op) => $op['type'] === 'UPDATE');
        $update = array_values($updates)[0];
        $this->assertTrue($update['has_db_raw']);
        $this->assertNotEmpty($update['db_raw_expressions']);
    }

    public function testExtractsWhereWithOperator(): void
    {
        $chain = "->where('age', '>', 18)";
        $conditions = $this->parser->extractWhereConditions($chain);
        $this->assertContains('age > 18', $conditions);
    }

    public function testExtractsOrWhereWithOperator(): void
    {
        $chain = "->orWhere('age', '<', 5)";
        $conditions = $this->parser->extractWhereConditions($chain);
        $this->assertContains('OR age < 5', $conditions);
    }
}
