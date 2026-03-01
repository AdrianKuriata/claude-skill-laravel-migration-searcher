<?php

namespace Tests\Unit\Parsers;

use DevSite\LaravelMigrationSearcher\Enums\DmlOperationType;
use DevSite\LaravelMigrationSearcher\Parsers\DmlParser;
use PHPUnit\Framework\TestCase;

class DmlParserTest extends TestCase
{
    protected DmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DmlParser();
    }

    public function testParseReturnsEmptyArrayForPlainMigration(): void
    {
        $content = "<?php\n\$table->id();\n\$table->string('name');\n";
        $this->assertSame([], $this->parser->parse($content));
    }

    public function testExtractsDbTableUpdate(): void
    {
        $content = "DB::table('users')->where('active', false)->update(['status' => 'inactive']);";
        $ops = $this->parser->parse($content);

        $updates = array_filter($ops, fn ($op) => $op->type === DmlOperationType::UPDATE);
        $this->assertNotEmpty($updates);

        $update = array_values($updates)[0];
        $this->assertSame('users', $update->table);
    }

    public function testExtractsDbTableInsert(): void
    {
        $content = "DB::table('settings')->whereNull('deleted_at')->insert(['key' => 'v']);";
        $ops = $this->parser->parse($content);

        $inserts = array_filter($ops, fn ($op) => $op->type === DmlOperationType::INSERT);
        $this->assertNotEmpty($inserts);
    }

    public function testExtractsDbTableDelete(): void
    {
        $content = "DB::table('users')->where('banned', true)->delete();";
        $ops = $this->parser->parse($content);

        $deletes = array_filter($ops, fn ($op) => $op->type === DmlOperationType::DELETE);
        $this->assertNotEmpty($deletes);
    }

    public function testExtractsEloquentCreate(): void
    {
        $content = '\App\Models\User::create([\'name\' => \'test\']);';
        $ops = $this->parser->parse($content);

        $creates = array_filter($ops, fn ($op) => $op->type === DmlOperationType::INSERT && $op->model !== null);
        $this->assertNotEmpty($creates);
        $this->assertSame('User', array_values($creates)[0]->model);
    }

    public function testExtractsEloquentSave(): void
    {
        $content = '$user->save();';
        $ops = $this->parser->parse($content);

        $saves = array_filter($ops, fn ($op) => $op->type === DmlOperationType::UPDATE_INSERT);
        $this->assertNotEmpty($saves);
        $this->assertSame('$user', array_values($saves)[0]->variable);
    }

    public function testExtractsEloquentRelationCreate(): void
    {
        $content = '$user->posts()->create([\'title\' => \'test\']);';
        $ops = $this->parser->parse($content);

        $creates = array_filter($ops, fn ($op) => $op->relation !== null);
        $this->assertNotEmpty($creates);
        $this->assertSame('posts', array_values($creates)[0]->relation);
    }

    public function testExtractsEloquentDelete(): void
    {
        $content = '$user->delete();';
        $ops = $this->parser->parse($content);

        $deletes = array_filter($ops, fn ($op) => $op->type === DmlOperationType::DELETE && $op->variable !== null);
        $this->assertNotEmpty($deletes);
    }

    public function testExtractsLoopOperations(): void
    {
        $content = 'foreach ($items as $item) { $item->save(); }';
        $ops = $this->parser->parse($content);

        $loops = array_filter($ops, fn ($op) => $op->type === DmlOperationType::LOOP);
        $this->assertNotEmpty($loops);
        $this->assertSame('foreach', array_values($loops)[0]->method);
    }

    public function testWhereConditionsExtractedViaUpdate(): void
    {
        $content = "DB::table('users')->where('active', true)->whereIn('status', ['a','b'])->whereNull('deleted_at')->update(['banned' => true]);";
        $ops = $this->parser->parse($content);

        $update = array_values(array_filter($ops, fn ($op) => $op->type === DmlOperationType::UPDATE))[0];
        $this->assertContains('active = true', $update->whereConditions);
        $this->assertContains('status IN (...)', $update->whereConditions);
        $this->assertContains('deleted_at IS NULL', $update->whereConditions);
    }

    public function testWhereNotNullConditionExtractedViaUpdate(): void
    {
        $content = "DB::table('users')->whereNotNull('email')->update(['verified' => true]);";
        $ops = $this->parser->parse($content);

        $update = array_values(array_filter($ops, fn ($op) => $op->type === DmlOperationType::UPDATE))[0];
        $this->assertContains('email IS NOT NULL', $update->whereConditions);
    }

    public function testWhereNotInConditionExtractedViaDelete(): void
    {
        $content = "DB::table('users')->whereNotIn('role', ['admin'])->delete();";
        $ops = $this->parser->parse($content);

        $delete = array_values(array_filter($ops, fn ($op) => $op->type === DmlOperationType::DELETE))[0];
        $this->assertContains('role NOT IN (...)', $delete->whereConditions);
    }

    public function testWhereBetweenConditionExtractedViaUpdate(): void
    {
        $content = "DB::table('users')->whereBetween('age', [18, 65])->update(['group' => 'adult']);";
        $ops = $this->parser->parse($content);

        $update = array_values(array_filter($ops, fn ($op) => $op->type === DmlOperationType::UPDATE))[0];
        $this->assertContains('age BETWEEN (...)', $update->whereConditions);
    }

    public function testWhereHasConditionExtractedViaDelete(): void
    {
        $content = "DB::table('users')->whereHas('posts')->delete();";
        $ops = $this->parser->parse($content);

        $delete = array_values(array_filter($ops, fn ($op) => $op->type === DmlOperationType::DELETE))[0];
        $this->assertContains('HAS posts', $delete->whereConditions);
    }

    public function testWhereDoesntHaveConditionExtractedViaDelete(): void
    {
        $content = "DB::table('users')->whereDoesntHave('orders')->delete();";
        $ops = $this->parser->parse($content);

        $delete = array_values(array_filter($ops, fn ($op) => $op->type === DmlOperationType::DELETE))[0];
        $this->assertContains("DOESN'T HAVE orders", $delete->whereConditions);
    }

    public function testOrWhereConditionExtractedViaUpdate(): void
    {
        $content = "DB::table('users')->orWhere('banned', true)->update(['active' => false]);";
        $ops = $this->parser->parse($content);

        $update = array_values(array_filter($ops, fn ($op) => $op->type === DmlOperationType::UPDATE))[0];
        $this->assertContains('OR banned = true', $update->whereConditions);
    }

    public function testTruncatesLongWhereValues(): void
    {
        $longValue = str_repeat('x', 60);
        $content = "DB::table('users')->where('name', '{$longValue}')->update(['x' => 1]);";
        $ops = $this->parser->parse($content);

        $update = array_values(array_filter($ops, fn ($op) => $op->type === DmlOperationType::UPDATE))[0];
        $this->assertStringContainsString('...', $update->whereConditions[0]);
    }

    public function testTruncatesLongOrWhereValues(): void
    {
        $longValue = str_repeat('y', 60);
        $content = "DB::table('users')->orWhere('email', '{$longValue}')->update(['x' => 1]);";
        $ops = $this->parser->parse($content);

        $update = array_values(array_filter($ops, fn ($op) => $op->type === DmlOperationType::UPDATE))[0];
        $found = array_filter($update->whereConditions, fn ($c) => str_contains($c, '...'));
        $this->assertNotEmpty($found);
    }

    public function testColumnsExtractedFromUpdateData(): void
    {
        $content = "DB::table('users')->where('id', 1)->update(['name' => 'test', 'email' => 'foo@bar.com']);";
        $ops = $this->parser->parse($content);

        $update = array_values(array_filter($ops, fn ($op) => $op->type === DmlOperationType::UPDATE))[0];
        $this->assertContains('name', $update->columnsUpdated);
        $this->assertContains('email', $update->columnsUpdated);
    }

    public function testDataPreviewCleanedInUpdate(): void
    {
        $content = "DB::table('users')->where('id', 1)->update([   'name' => 'test'   ]);";
        $ops = $this->parser->parse($content);

        $update = array_values(array_filter($ops, fn ($op) => $op->type === DmlOperationType::UPDATE))[0];
        $this->assertNotNull($update->dataPreview);
        $this->assertStringNotContainsString('   ', $update->dataPreview);
    }

    public function testDataPreviewTruncatesLongUpdateData(): void
    {
        $longValue = str_repeat('x', 200);
        $content = "DB::table('users')->where('id', 1)->update(['name' => '{$longValue}']);";
        $ops = $this->parser->parse($content);

        $update = array_values(array_filter($ops, fn ($op) => $op->type === DmlOperationType::UPDATE))[0];
        $this->assertStringEndsWith('...', $update->dataPreview);
        $this->assertLessThanOrEqual(153, strlen($update->dataPreview));
    }

    public function testLoopWithCreateOperation(): void
    {
        $content = 'foreach ($items as $item) { $item->posts()->create([\'title\' => \'x\']); }';
        $ops = $this->parser->parse($content);

        $loops = array_filter($ops, fn ($op) => $op->type === DmlOperationType::LOOP);
        $this->assertNotEmpty($loops);
        $loop = array_values($loops)[0];
        $this->assertContains('create()', $loop->operationsInLoop);
    }

    public function testLoopWithDeleteOperation(): void
    {
        $content = 'foreach ($items as $item) { $item->delete(); }';
        $ops = $this->parser->parse($content);

        $loops = array_filter($ops, fn ($op) => $op->type === DmlOperationType::LOOP);
        $this->assertNotEmpty($loops);
        $loop = array_values($loops)[0];
        $this->assertContains('delete()', $loop->operationsInLoop);
    }

    public function testLoopWithUpdateOperation(): void
    {
        $content = 'foreach ($items as $item) { $item->update([\'active\' => true]); }';
        $ops = $this->parser->parse($content);

        $loops = array_filter($ops, fn ($op) => $op->type === DmlOperationType::LOOP);
        $this->assertNotEmpty($loops);
        $loop = array_values($loops)[0];
        $this->assertContains('update()', $loop->operationsInLoop);
    }

    public function testLoopWithoutDmlOperationsIsIgnored(): void
    {
        $content = 'foreach ($items as $item) { echo $item; }';
        $ops = $this->parser->parse($content);

        $loops = array_filter($ops, fn ($op) => $op->type === DmlOperationType::LOOP);
        $this->assertEmpty($loops);
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
        $ops = $this->parser->parse($content);

        $updates = array_filter($ops, fn ($op) => $op->type === DmlOperationType::UPDATE);
        $update = array_values($updates)[0];
        $this->assertTrue($update->hasDbRaw);
        $this->assertNotEmpty($update->dbRawExpressions);
    }

    public function testExtractsWhereWithOperator(): void
    {
        $content = "DB::table('users')->where('age', '>', 18)->update(['adult' => true]);";
        $ops = $this->parser->parse($content);

        $update = array_values(array_filter($ops, fn ($op) => $op->type === DmlOperationType::UPDATE))[0];
        $this->assertContains('age > 18', $update->whereConditions);
    }

    public function testExtractsOrWhereWithOperator(): void
    {
        $content = "DB::table('users')->orWhere('age', '<', 5)->update(['minor' => true]);";
        $ops = $this->parser->parse($content);

        $update = array_values(array_filter($ops, fn ($op) => $op->type === DmlOperationType::UPDATE))[0];
        $this->assertContains('OR age < 5', $update->whereConditions);
    }
}
