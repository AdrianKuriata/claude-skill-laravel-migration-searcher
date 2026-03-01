<?php

namespace Tests\Unit\DTOs;

use DevSite\LaravelMigrationSearcher\DTOs\DdlOperation;
use DevSite\LaravelMigrationSearcher\Enums\DdlCategory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DdlOperationTest extends TestCase
{
    #[Test]
    public function itStoresMethodParamsAndCategory(): void
    {
        $op = new DdlOperation('string', ["'name'"], DdlCategory::COLUMN_CREATE);

        $this->assertSame('string', $op->method);
        $this->assertSame(["'name'"], $op->params);
        $this->assertSame(DdlCategory::COLUMN_CREATE, $op->category);
    }

    #[Test]
    public function itConvertsToArrayWithEnumValue(): void
    {
        $op = new DdlOperation('index', ["'email'"], DdlCategory::INDEX);

        $result = $op->toArray();

        $this->assertSame('index', $result['method']);
        $this->assertSame(["'email'"], $result['params']);
        $this->assertSame('index', $result['category']);
    }
}
