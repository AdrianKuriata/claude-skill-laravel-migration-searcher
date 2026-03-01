<?php

namespace Tests\Unit\DTOs;

use DevSite\LaravelMigrationSearcher\DTOs\IndexDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IndexDefinitionTest extends TestCase
{
    #[Test]
    public function itStoresTypeAndDefinition(): void
    {
        $index = new IndexDefinition('unique', "'email'");

        $this->assertSame('unique', $index->type);
        $this->assertSame("'email'", $index->definition);
    }

    #[Test]
    public function itConvertsToArray(): void
    {
        $index = new IndexDefinition('index', "'name'");

        $result = $index->toArray();

        $this->assertSame('index', $result['type']);
        $this->assertSame("'name'", $result['definition']);
    }
}
