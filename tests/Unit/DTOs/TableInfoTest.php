<?php

namespace Tests\Unit\DTOs;

use DevSite\LaravelMigrationSearcher\DTOs\TableInfo;
use DevSite\LaravelMigrationSearcher\Enums\TableOperation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TableInfoTest extends TestCase
{
    #[Test]
    public function itStoresOperationAndMethods(): void
    {
        $info = new TableInfo(TableOperation::CREATE, ['id', 'string']);

        $this->assertSame(TableOperation::CREATE, $info->operation);
        $this->assertSame(['id', 'string'], $info->methods);
    }

    #[Test]
    public function itConvertsToArrayWithEnumValue(): void
    {
        $info = new TableInfo(TableOperation::ALTER, ['addColumn']);

        $result = $info->toArray();

        $this->assertSame('ALTER', $result['operation']);
        $this->assertSame(['addColumn'], $result['methods']);
    }

    #[Test]
    public function itIsReadonly(): void
    {
        $ref = new \ReflectionClass(TableInfo::class);

        $this->assertTrue($ref->isReadonly());
        $this->assertTrue($ref->isFinal());
    }
}
