<?php

namespace Tests\Unit\Parsers;

use DevSite\LaravelMigrationSearcher\Services\Parsers\DependencyParser;
use PHPUnit\Framework\TestCase;

class DependencyParserTest extends TestCase
{
    protected DependencyParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DependencyParser();
    }

    public function testParseDelegatesToExtractDependencies(): void
    {
        $content = '<?php' . "\n";
        $this->assertSame(
            $this->parser->extractDependencies($content),
            $this->parser->parse($content)
        );
    }

    public function testExtractsRequiresAnnotation(): void
    {
        $content = "// @requires create_users_table\n";
        $deps = $this->parser->extractDependencies($content);

        $this->assertArrayHasKey('requires', $deps);
        $this->assertContains('create_users_table', $deps['requires']);
    }

    public function testExtractsDependsOnAnnotation(): void
    {
        $content = "// @depends on create_roles_table\n";
        $deps = $this->parser->extractDependencies($content);

        $this->assertArrayHasKey('depends_on', $deps);
        $this->assertContains('create_roles_table', $deps['depends_on']);
    }

    public function testExtractsForeignKeyDependencies(): void
    {
        $content = "\$table->foreign('user_id')->references('id')->on('users');";
        $deps = $this->parser->extractDependencies($content);

        $this->assertArrayHasKey('foreign_keys', $deps);
        $this->assertCount(1, $deps['foreign_keys']);
        $this->assertSame('user_id', $deps['foreign_keys'][0]['column']);
        $this->assertSame('id', $deps['foreign_keys'][0]['references']);
        $this->assertSame('users', $deps['foreign_keys'][0]['on_table']);
    }

    public function testEmptyContentReturnsNoDependencies(): void
    {
        $this->assertEmpty($this->parser->extractDependencies("<?php\n"));
    }

    public function testMultipleRequiresAnnotations(): void
    {
        $content = "// @requires create_users_table\n// @requires create_roles_table\n";
        $deps = $this->parser->extractDependencies($content);

        $this->assertCount(2, $deps['requires']);
    }
}
