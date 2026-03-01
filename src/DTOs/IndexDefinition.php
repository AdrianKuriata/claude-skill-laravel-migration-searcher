<?php

namespace DevSite\LaravelMigrationSearcher\DTOs;

final readonly class IndexDefinition extends BaseDTO
{
    public function __construct(
        public string $type,
        public string $definition,
    ) {
    }
}
