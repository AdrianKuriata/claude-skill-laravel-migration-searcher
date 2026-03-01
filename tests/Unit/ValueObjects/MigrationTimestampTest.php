<?php

namespace Tests\Unit\ValueObjects;

use DevSite\LaravelMigrationSearcher\Contracts\Support\ScalarValueObject;
use DevSite\LaravelMigrationSearcher\ValueObjects\MigrationTimestamp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MigrationTimestampTest extends TestCase
{
    #[Test]
    public function itAcceptsValidTimestamp(): void
    {
        $ts = new MigrationTimestamp('2024_01_15_143022');

        $this->assertSame('2024_01_15_143022', $ts->value);
    }

    #[Test]
    public function itAcceptsUnknown(): void
    {
        $ts = new MigrationTimestamp('unknown');

        $this->assertSame('unknown', $ts->value);
        $this->assertTrue($ts->isUnknown());
    }

    #[Test]
    public function itRejectsInvalidFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Migration timestamp must match format YYYY_MM_DD_HHMMSS or be 'unknown'");

        new MigrationTimestamp('2024-01-15');
    }

    #[Test]
    public function itRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MigrationTimestamp('');
    }

    #[Test]
    public function itRejectsPartialTimestamp(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MigrationTimestamp('2024_01_15');
    }

    #[Test]
    public function itKnowsWhenNotUnknown(): void
    {
        $ts = new MigrationTimestamp('2024_01_15_143022');

        $this->assertFalse($ts->isUnknown());
    }

    #[Test]
    public function itConvertsToString(): void
    {
        $ts = new MigrationTimestamp('2024_01_15_143022');

        $this->assertSame('2024_01_15_143022', (string) $ts);
    }

    #[Test]
    public function itImplementsScalarValueObject(): void
    {
        $ts = new MigrationTimestamp('2024_01_15_143022');

        $this->assertInstanceOf(ScalarValueObject::class, $ts);
    }

    #[Test]
    public function itReturnsScalarValue(): void
    {
        $ts = new MigrationTimestamp('2024_01_15_143022');

        $this->assertSame('2024_01_15_143022', $ts->toScalar());
    }

    #[Test]
    public function itIsReadonly(): void
    {
        $ref = new \ReflectionClass(MigrationTimestamp::class);

        $this->assertTrue($ref->isReadonly());
        $this->assertTrue($ref->isFinal());
    }
}
