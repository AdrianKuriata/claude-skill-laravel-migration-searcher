<?php

namespace Tests\Unit\ValueObjects;

use DevSite\LaravelMigrationSearcher\Contracts\Support\ScalarValueObject;
use DevSite\LaravelMigrationSearcher\ValueObjects\ComplexityScore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ComplexityScoreTest extends TestCase
{
    #[Test]
    public function itAcceptsValidScores(): void
    {
        $min = new ComplexityScore(1);
        $max = new ComplexityScore(10);
        $mid = new ComplexityScore(5);

        $this->assertSame(1, $min->value);
        $this->assertSame(10, $max->value);
        $this->assertSame(5, $mid->value);
    }

    #[Test]
    public function itRejectsScoreBelowMinimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Complexity score must be between 1 and 10, got 0');

        new ComplexityScore(0);
    }

    #[Test]
    public function itRejectsScoreAboveMaximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Complexity score must be between 1 and 10, got 11');

        new ComplexityScore(11);
    }

    #[Test]
    public function itRejectsNegativeScore(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ComplexityScore(-1);
    }

    #[Test]
    public function itConvertsToString(): void
    {
        $score = new ComplexityScore(7);

        $this->assertSame('7', (string) $score);
    }

    #[Test]
    public function itImplementsScalarValueObject(): void
    {
        $score = new ComplexityScore(5);

        $this->assertInstanceOf(ScalarValueObject::class, $score);
    }

    #[Test]
    public function itReturnsScalarValue(): void
    {
        $score = new ComplexityScore(7);

        $this->assertSame(7, $score->toScalar());
    }

    #[Test]
    public function itIsReadonly(): void
    {
        $ref = new \ReflectionClass(ComplexityScore::class);

        $this->assertTrue($ref->isReadonly());
        $this->assertTrue($ref->isFinal());
    }
}
