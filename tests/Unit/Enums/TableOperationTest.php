<?php

namespace Tests\Unit\Enums;

use DevSite\LaravelMigrationSearcher\Enums\TableOperation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TableOperationTest extends TestCase
{
    #[Test]
    public function itHasAllExpectedCases(): void
    {
        $cases = TableOperation::cases();

        $this->assertCount(5, $cases);
        $this->assertSame('CREATE', TableOperation::CREATE->value);
        $this->assertSame('ALTER', TableOperation::ALTER->value);
        $this->assertSame('DROP', TableOperation::DROP->value);
        $this->assertSame('RENAME', TableOperation::RENAME->value);
        $this->assertSame('DATA', TableOperation::DATA->value);
    }

    #[Test]
    public function itCanBeCreatedFromString(): void
    {
        $this->assertSame(TableOperation::CREATE, TableOperation::from('CREATE'));
        $this->assertSame(TableOperation::ALTER, TableOperation::from('ALTER'));
        $this->assertSame(TableOperation::DROP, TableOperation::from('DROP'));
        $this->assertSame(TableOperation::RENAME, TableOperation::from('RENAME'));
        $this->assertSame(TableOperation::DATA, TableOperation::from('DATA'));
    }

    #[Test]
    public function itReturnsNullForInvalidString(): void
    {
        $this->assertNull(TableOperation::tryFrom('INVALID'));
    }

    #[Test]
    public function itReturnsLabel(): void
    {
        $this->assertSame('Table Creation', TableOperation::CREATE->label());
        $this->assertSame('Structure Modifications', TableOperation::ALTER->label());
        $this->assertSame('Table Deletion', TableOperation::DROP->label());
        $this->assertSame('Renaming', TableOperation::RENAME->label());
        $this->assertSame('Data Modifications', TableOperation::DATA->label());
    }
}
