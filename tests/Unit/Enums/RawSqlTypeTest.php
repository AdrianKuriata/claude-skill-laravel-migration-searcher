<?php

namespace Tests\Unit\Enums;

use DevSite\LaravelMigrationSearcher\Enums\RawSqlType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RawSqlTypeTest extends TestCase
{
    #[Test]
    public function itHasAllExpectedCases(): void
    {
        $cases = RawSqlType::cases();

        $this->assertCount(4, $cases);
        $this->assertSame('statement', RawSqlType::STATEMENT->value);
        $this->assertSame('unprepared', RawSqlType::UNPREPARED->value);
        $this->assertSame('raw', RawSqlType::RAW->value);
        $this->assertSame('heredoc', RawSqlType::HEREDOC->value);
    }

    #[Test]
    public function itCanBeCreatedFromString(): void
    {
        $this->assertSame(RawSqlType::STATEMENT, RawSqlType::from('statement'));
        $this->assertSame(RawSqlType::RAW, RawSqlType::from('raw'));
    }

    #[Test]
    public function itReturnsNullForInvalidString(): void
    {
        $this->assertNull(RawSqlType::tryFrom('INVALID'));
    }
}
