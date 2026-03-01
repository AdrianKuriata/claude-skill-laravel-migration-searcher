<?php

namespace Tests\Unit\Enums;

use DevSite\LaravelMigrationSearcher\Enums\SqlOperationType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SqlOperationTypeTest extends TestCase
{
    #[Test]
    public function itHasAllExpectedCases(): void
    {
        $cases = SqlOperationType::cases();

        $this->assertCount(10, $cases);
        $this->assertSame('SELECT', SqlOperationType::SELECT->value);
        $this->assertSame('INSERT', SqlOperationType::INSERT->value);
        $this->assertSame('UPDATE', SqlOperationType::UPDATE->value);
        $this->assertSame('DELETE', SqlOperationType::DELETE->value);
        $this->assertSame('CREATE', SqlOperationType::CREATE->value);
        $this->assertSame('ALTER', SqlOperationType::ALTER->value);
        $this->assertSame('DROP', SqlOperationType::DROP->value);
        $this->assertSame('TRUNCATE', SqlOperationType::TRUNCATE->value);
        $this->assertSame('EXPRESSION', SqlOperationType::EXPRESSION->value);
        $this->assertSame('OTHER', SqlOperationType::OTHER->value);
    }

    #[Test]
    public function itCanBeCreatedFromString(): void
    {
        $this->assertSame(SqlOperationType::SELECT, SqlOperationType::from('SELECT'));
        $this->assertSame(SqlOperationType::EXPRESSION, SqlOperationType::from('EXPRESSION'));
    }

    #[Test]
    public function itReturnsNullForInvalidString(): void
    {
        $this->assertNull(SqlOperationType::tryFrom('INVALID'));
    }
}
