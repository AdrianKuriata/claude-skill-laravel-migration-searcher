<?php

namespace DevSite\LaravelMigrationSearcher\DTOs;

use DevSite\LaravelMigrationSearcher\Enums\DdlCategory;

final readonly class DdlOperation extends BaseDTO
{
    /** @param string[] $params */
    public function __construct(
        public string $method,
        public array $params,
        public DdlCategory $category,
    ) {
    }
}
