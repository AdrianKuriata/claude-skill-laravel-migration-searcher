<?php

namespace DevSite\LaravelMigrationSearcher\DTOs;

use DevSite\LaravelMigrationSearcher\Enums\DdlCategory;

final readonly class DdlOperation extends BaseDTO
{
    public function __construct(
        public string $method,
        public array $params,
        public DdlCategory $category,
    ) {
    }
}
