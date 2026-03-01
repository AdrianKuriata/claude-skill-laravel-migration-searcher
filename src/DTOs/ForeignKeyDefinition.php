<?php

namespace DevSite\LaravelMigrationSearcher\DTOs;

final readonly class ForeignKeyDefinition extends BaseDTO
{
    public function __construct(
        public string $column,
        public ?string $references,
        public ?string $onTable,
    ) {
    }
}
