<?php

namespace Tests\Unit\DTOs;

use DevSite\LaravelMigrationSearcher\DTOs\ColumnDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ColumnDefinitionTest extends TestCase
{
    #[Test]
    public function itStoresTypeAndModifiers(): void
    {
        $col = new ColumnDefinition('string', ['nullable', 'unique']);

        $this->assertSame('string', $col->type);
        $this->assertSame(['nullable', 'unique'], $col->modifiers);
    }

    #[Test]
    public function itConvertsToArray(): void
    {
        $col = new ColumnDefinition('integer', ['unsigned']);

        $result = $col->toArray();

        $this->assertSame('integer', $result['type']);
        $this->assertSame(['unsigned'], $result['modifiers']);
    }
}
