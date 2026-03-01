<?php

namespace DevSite\LaravelMigrationSearcher\DTOs;

use DevSite\LaravelMigrationSearcher\Enums\RawSqlType;
use DevSite\LaravelMigrationSearcher\Enums\SqlOperationType;

final readonly class RawSqlStatement extends BaseDTO
{
    public function __construct(
        public RawSqlType $type,
        public string $sql,
        public SqlOperationType $operation,
    ) {
    }
}
