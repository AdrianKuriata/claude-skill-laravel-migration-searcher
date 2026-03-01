<?php

namespace Tests\Unit\DTOs;

use DevSite\LaravelMigrationSearcher\DTOs\ForeignKeyDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ForeignKeyDefinitionTest extends TestCase
{
    #[Test]
    public function itStoresFullForeignKey(): void
    {
        $fk = new ForeignKeyDefinition('user_id', 'id', 'users');

        $this->assertSame('user_id', $fk->column);
        $this->assertSame('id', $fk->references);
        $this->assertSame('users', $fk->onTable);
    }

    #[Test]
    public function itHandlesNullableFields(): void
    {
        $fk = new ForeignKeyDefinition('user_id', null, null);

        $this->assertSame('user_id', $fk->column);
        $this->assertNull($fk->references);
        $this->assertNull($fk->onTable);
    }

    #[Test]
    public function itConvertsToArray(): void
    {
        $fk = new ForeignKeyDefinition('user_id', 'id', 'users');

        $result = $fk->toArray();

        $this->assertSame('user_id', $result['column']);
        $this->assertSame('id', $result['references']);
        $this->assertSame('users', $result['on_table']);
    }
}
