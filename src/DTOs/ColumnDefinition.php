<?php

namespace DevSite\LaravelMigrationSearcher\DTOs;

final readonly class ColumnDefinition extends BaseDTO
{
    public function __construct(
        public string $type,
        public array $modifiers,
    ) {
    }
}
