<?php

namespace DevSite\LaravelMigrationSearcher\DTOs;

use DevSite\LaravelMigrationSearcher\Enums\TableOperation;

final readonly class TableInfo extends BaseDTO
{
    public function __construct(
        public TableOperation $operation,
        public array $methods,
    ) {
    }
}
