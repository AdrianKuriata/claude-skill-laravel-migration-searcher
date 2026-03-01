<?php

namespace Tests\Unit\DTOs;

use DevSite\LaravelMigrationSearcher\DTOs\RawSqlStatement;
use DevSite\LaravelMigrationSearcher\Enums\RawSqlType;
use DevSite\LaravelMigrationSearcher\Enums\SqlOperationType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RawSqlStatementTest extends TestCase
{
    #[Test]
    public function itStoresTypeAndSqlAndOperation(): void
    {
        $stmt = new RawSqlStatement(
            RawSqlType::STATEMENT,
            'CREATE INDEX idx ON users (email)',
            SqlOperationType::CREATE,
        );

        $this->assertSame(RawSqlType::STATEMENT, $stmt->type);
        $this->assertSame('CREATE INDEX idx ON users (email)', $stmt->sql);
        $this->assertSame(SqlOperationType::CREATE, $stmt->operation);
    }

    #[Test]
    public function itConvertsToArrayWithEnumValues(): void
    {
        $stmt = new RawSqlStatement(
            RawSqlType::RAW,
            'COALESCE(a, b)',
            SqlOperationType::EXPRESSION,
        );

        $result = $stmt->toArray();

        $this->assertSame('raw', $result['type']);
        $this->assertSame('COALESCE(a, b)', $result['sql']);
        $this->assertSame('EXPRESSION', $result['operation']);
    }
}
