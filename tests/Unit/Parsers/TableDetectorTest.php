<?php

namespace Tests\Unit\Parsers;

use DevSite\LaravelMigrationSearcher\Services\Parsers\TableDetector;
use PHPUnit\Framework\TestCase;

class TableDetectorTest extends TestCase
{
    protected TableDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new TableDetector();
    }

    public function testDetectsSchemaCreate(): void
    {
        $content = "Schema::create('users', function (\$table) { \$table->id(); });";
        $tables = $this->detector->parse($content);

        $this->assertArrayHasKey('users', $tables);
        $this->assertSame('CREATE', $tables['users']['operation']);
    }

    public function testDetectsSchemaTable(): void
    {
        $content = "Schema::table('users', function (\$table) { \$table->string('name'); });";
        $tables = $this->detector->parse($content);

        $this->assertArrayHasKey('users', $tables);
        $this->assertSame('ALTER', $tables['users']['operation']);
    }

    public function testDetectsSchemaDropIfExists(): void
    {
        $content = "Schema::dropIfExists('legacy');";
        $tables = $this->detector->parse($content);

        $this->assertArrayHasKey('legacy', $tables);
        $this->assertSame('DROP', $tables['legacy']['operation']);
    }

    public function testDetectsSchemaDrop(): void
    {
        $content = "Schema::drop('legacy');";
        $tables = $this->detector->parse($content);

        $this->assertArrayHasKey('legacy', $tables);
        $this->assertSame('DROP', $tables['legacy']['operation']);
    }

    public function testDetectsSchemaRename(): void
    {
        $content = "Schema::rename('orders', 'sales_orders');";
        $tables = $this->detector->parse($content);

        $this->assertArrayHasKey('orders', $tables);
        $this->assertSame('RENAME', $tables['orders']['operation']);
    }

    public function testDetectsDbTable(): void
    {
        $content = "DB::table('users')->where('active', true)->get();";
        $tables = $this->detector->parse($content);

        $this->assertArrayHasKey('users', $tables);
        $this->assertSame('DATA', $tables['users']['operation']);
    }

    public function testSchemaCreateTakesPrecedenceOverDbTable(): void
    {
        $content = "Schema::create('items', function (\$table) { \$table->id(); });\nDB::table('items')->insert([]);";
        $tables = $this->detector->parse($content);

        $this->assertSame('CREATE', $tables['items']['operation']);
    }

    public function testSchemaTableNotOverriddenByDbTable(): void
    {
        $content = "Schema::table('users', function (\$table) { \$table->string('x'); });\nDB::table('users')->get();";
        $tables = $this->detector->parse($content);

        $this->assertSame('ALTER', $tables['users']['operation']);
    }

    public function testEmptyContentReturnsNoTables(): void
    {
        $this->assertEmpty($this->detector->parse("<?php\n"));
    }

    public function testMultipleTablesDetected(): void
    {
        $content = "Schema::create('a', function (\$t) {});\nSchema::create('b', function (\$t) {});";
        $tables = $this->detector->parse($content);

        $this->assertCount(2, $tables);
        $this->assertArrayHasKey('a', $tables);
        $this->assertArrayHasKey('b', $tables);
    }
}
