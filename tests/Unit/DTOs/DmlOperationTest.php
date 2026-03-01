<?php

namespace Tests\Unit\DTOs;

use DevSite\LaravelMigrationSearcher\DTOs\DmlOperation;
use DevSite\LaravelMigrationSearcher\Enums\DmlOperationType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DmlOperationTest extends TestCase
{
    #[Test]
    public function itStoresDbTableUpdate(): void
    {
        $op = new DmlOperation(
            type: DmlOperationType::UPDATE,
            table: 'users',
            whereConditions: ['status = active'],
            columnsUpdated: ['name'],
            hasDbRaw: false,
            dataPreview: "['name' => 'test']",
        );

        $this->assertSame(DmlOperationType::UPDATE, $op->type);
        $this->assertSame('users', $op->table);
        $this->assertSame(['status = active'], $op->whereConditions);
        $this->assertSame(['name'], $op->columnsUpdated);
        $this->assertFalse($op->hasDbRaw);
    }

    #[Test]
    public function itStoresEloquentOperation(): void
    {
        $op = new DmlOperation(
            type: DmlOperationType::INSERT,
            model: 'User',
            method: 'Eloquent::create',
            note: 'Static Model::create() call',
        );

        $this->assertSame(DmlOperationType::INSERT, $op->type);
        $this->assertSame('User', $op->model);
        $this->assertSame('Eloquent::create', $op->method);
    }

    #[Test]
    public function itStoresLoopOperation(): void
    {
        $op = new DmlOperation(
            type: DmlOperationType::LOOP,
            method: 'foreach',
            note: 'Loop operations: save()',
            operationsInLoop: ['save() na $user'],
        );

        $this->assertSame(DmlOperationType::LOOP, $op->type);
        $this->assertSame(['save() na $user'], $op->operationsInLoop);
    }

    #[Test]
    public function itConvertsToArrayWithEnumValue(): void
    {
        $op = new DmlOperation(
            type: DmlOperationType::DELETE,
            table: 'users',
            whereConditions: ['id = 1'],
        );

        $result = $op->toArray();

        $this->assertSame('DELETE', $result['type']);
        $this->assertSame('users', $result['table']);
        $this->assertSame(['id = 1'], $result['where_conditions']);
    }

    #[Test]
    public function itDefaultsOptionalFieldsToNull(): void
    {
        $op = new DmlOperation(type: DmlOperationType::INSERT);

        $this->assertNull($op->table);
        $this->assertNull($op->model);
        $this->assertNull($op->variable);
        $this->assertNull($op->relation);
        $this->assertNull($op->method);
        $this->assertNull($op->note);
        $this->assertNull($op->dataPreview);
        $this->assertEmpty($op->whereConditions);
        $this->assertEmpty($op->columnsUpdated);
        $this->assertFalse($op->hasDbRaw);
        $this->assertEmpty($op->dbRawExpressions);
        $this->assertEmpty($op->operationsInLoop);
    }
}
