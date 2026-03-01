<?php

namespace Tests\Unit\Enums;

use DevSite\LaravelMigrationSearcher\Enums\DdlCategory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DdlCategoryTest extends TestCase
{
    #[Test]
    public function itHasAllExpectedCases(): void
    {
        $cases = DdlCategory::cases();

        $this->assertCount(7, $cases);
        $this->assertSame('column_create', DdlCategory::COLUMN_CREATE->value);
        $this->assertSame('column_modify', DdlCategory::COLUMN_MODIFY->value);
        $this->assertSame('index', DdlCategory::INDEX->value);
        $this->assertSame('index_drop', DdlCategory::INDEX_DROP->value);
        $this->assertSame('foreign_key', DdlCategory::FOREIGN_KEY->value);
        $this->assertSame('foreign_key_drop', DdlCategory::FOREIGN_KEY_DROP->value);
        $this->assertSame('other', DdlCategory::OTHER->value);
    }

    #[Test]
    public function itCanBeCreatedFromString(): void
    {
        $this->assertSame(DdlCategory::COLUMN_CREATE, DdlCategory::from('column_create'));
        $this->assertSame(DdlCategory::OTHER, DdlCategory::from('other'));
    }

    #[Test]
    public function itReturnsNullForInvalidString(): void
    {
        $this->assertNull(DdlCategory::tryFrom('INVALID'));
    }
}
