<?php

namespace Tests\Unit\Parsers;

use DevSite\LaravelMigrationSearcher\Services\Parsers\DdlParser;
use PHPUnit\Framework\TestCase;

class DdlParserTest extends TestCase
{
    protected DdlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DdlParser();
    }

    public function testParseDelegatesToExtractDDLOperations(): void
    {
        $content = '$table->string(\'name\')';
        $this->assertSame(
            $this->parser->extractDDLOperations($content),
            $this->parser->parse($content)
        );
    }

    public function testExtractsDDLOperations(): void
    {
        $content = '$table->id();' . "\n" . '$table->string(\'name\');';
        $ops = $this->parser->extractDDLOperations($content);

        $methods = array_column($ops, 'method');
        $this->assertContains('id', $methods);
        $this->assertContains('string', $methods);
    }

    public function testCategorizeMethodColumnCreate(): void
    {
        $this->assertSame('column_create', $this->parser->categorizeMethod('string'));
        $this->assertSame('column_create', $this->parser->categorizeMethod('id'));
        $this->assertSame('column_create', $this->parser->categorizeMethod('foreignId'));
    }

    public function testCategorizeMethodColumnModify(): void
    {
        $this->assertSame('column_modify', $this->parser->categorizeMethod('dropColumn'));
        $this->assertSame('column_modify', $this->parser->categorizeMethod('renameColumn'));
    }

    public function testCategorizeMethodIndex(): void
    {
        $this->assertSame('index', $this->parser->categorizeMethod('index'));
        $this->assertSame('index', $this->parser->categorizeMethod('unique'));
        $this->assertSame('index', $this->parser->categorizeMethod('primary'));
    }

    public function testCategorizeMethodIndexDrop(): void
    {
        $this->assertSame('index_drop', $this->parser->categorizeMethod('dropIndex'));
        $this->assertSame('index_drop', $this->parser->categorizeMethod('dropUnique'));
    }

    public function testCategorizeMethodForeignKey(): void
    {
        $this->assertSame('foreign_key', $this->parser->categorizeMethod('foreign'));
    }

    public function testCategorizeMethodForeignKeyDrop(): void
    {
        $this->assertSame('foreign_key_drop', $this->parser->categorizeMethod('dropForeign'));
    }

    public function testCategorizeMethodOther(): void
    {
        $this->assertSame('other', $this->parser->categorizeMethod('timestamps'));
        $this->assertSame('other', $this->parser->categorizeMethod('softDeletes'));
    }

    public function testParseMethodParamsEmpty(): void
    {
        $this->assertSame([], $this->parser->parseMethodParams(''));
        $this->assertSame([], $this->parser->parseMethodParams('   '));
    }

    public function testParseMethodParamsWithValues(): void
    {
        $result = $this->parser->parseMethodParams("'name', 255");
        $this->assertSame(["'name'", '255'], $result);
    }

    public function testExtractColumns(): void
    {
        $content = '$table->string(\'name\');' . "\n" . '$table->boolean(\'active\');';
        $columns = $this->parser->extractColumns($content);

        $this->assertArrayHasKey('name', $columns);
        $this->assertSame('string', $columns['name']['type']);
        $this->assertArrayHasKey('active', $columns);
        $this->assertSame('boolean', $columns['active']['type']);
    }

    public function testExtractColumnModifiers(): void
    {
        $definition = '$table->string(\'name\')->nullable()->default(\'test\')->unique()->unsigned()->index()->primary()';
        $modifiers = $this->parser->extractColumnModifiers($definition);

        $this->assertContains('nullable', $modifiers);
        $this->assertContains('unique', $modifiers);
        $this->assertContains('unsigned', $modifiers);
        $this->assertContains('indexed', $modifiers);
        $this->assertContains('primary', $modifiers);

        $defaultFound = false;
        foreach ($modifiers as $mod) {
            if (str_starts_with($mod, 'default(')) {
                $defaultFound = true;
                break;
            }
        }
        $this->assertTrue($defaultFound);
    }

    public function testExtractColumnModifiersEmpty(): void
    {
        $definition = '$table->string(\'name\')';
        $this->assertEmpty($this->parser->extractColumnModifiers($definition));
    }

    public function testExtractIndexes(): void
    {
        $content = '$table->index(\'email\');' . "\n" . '$table->unique([\'email\', \'tenant_id\']);';
        $indexes = $this->parser->extractIndexes($content);

        $this->assertCount(2, $indexes);
        $this->assertSame('index', $indexes[0]['type']);
        $this->assertSame('unique', $indexes[1]['type']);
    }

    public function testExtractForeignKeys(): void
    {
        $content = '$table->foreign(\'user_id\')->references(\'id\')->on(\'users\');';
        $fks = $this->parser->extractForeignKeys($content);

        $this->assertCount(1, $fks);
        $this->assertSame('user_id', $fks[0]['column']);
        $this->assertSame('id', $fks[0]['references']);
        $this->assertSame('users', $fks[0]['on_table']);
    }

    public function testExtractForeignKeysPartial(): void
    {
        $content = '$table->foreign(\'user_id\');';
        $fks = $this->parser->extractForeignKeys($content);

        $this->assertCount(1, $fks);
        $this->assertSame('user_id', $fks[0]['column']);
        $this->assertNull($fks[0]['references']);
        $this->assertNull($fks[0]['on_table']);
    }

    public function testExtractMethodsUsed(): void
    {
        $content = '$table->id();' . "\n" . '$table->string(\'name\');' . "\n" . '$table->timestamps();';
        $methods = $this->parser->extractMethodsUsed($content);

        $this->assertContains('id', $methods);
        $this->assertContains('string', $methods);
        $this->assertContains('timestamps', $methods);
    }

    public function testExtractMethodsUsedReturnsUniqueValues(): void
    {
        $content = '$table->string(\'a\');' . "\n" . '$table->string(\'b\');';
        $methods = $this->parser->extractMethodsUsed($content);

        $this->assertCount(1, $methods);
        $this->assertSame('string', $methods[0]);
    }
}
