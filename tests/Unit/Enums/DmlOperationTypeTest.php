<?php

namespace Tests\Unit\Enums;

use DevSite\LaravelMigrationSearcher\Enums\DmlOperationType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DmlOperationTypeTest extends TestCase
{
    #[Test]
    public function itHasAllExpectedCases(): void
    {
        $cases = DmlOperationType::cases();

        $this->assertCount(5, $cases);
        $this->assertSame('INSERT', DmlOperationType::INSERT->value);
        $this->assertSame('UPDATE', DmlOperationType::UPDATE->value);
        $this->assertSame('DELETE', DmlOperationType::DELETE->value);
        $this->assertSame('UPDATE/INSERT', DmlOperationType::UPDATE_INSERT->value);
        $this->assertSame('LOOP', DmlOperationType::LOOP->value);
    }

    #[Test]
    public function itCanBeCreatedFromString(): void
    {
        $this->assertSame(DmlOperationType::INSERT, DmlOperationType::from('INSERT'));
        $this->assertSame(DmlOperationType::UPDATE_INSERT, DmlOperationType::from('UPDATE/INSERT'));
    }

    #[Test]
    public function itReturnsNullForInvalidString(): void
    {
        $this->assertNull(DmlOperationType::tryFrom('INVALID'));
    }
}
