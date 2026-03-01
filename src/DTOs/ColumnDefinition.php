<?php

namespace DevSite\LaravelMigrationSearcher\DTOs;

final readonly class ColumnDefinition extends BaseDTO
{
    /** @param string[] $modifiers */
    public function __construct(
        public string $type,
        public array $modifiers,
    ) {
    }
}
