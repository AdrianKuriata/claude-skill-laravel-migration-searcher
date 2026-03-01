<?php

namespace DevSite\LaravelMigrationSearcher\DTOs;

final readonly class DependencyInfo extends BaseDTO
{
    public function __construct(
        public array $requires = [],
        public array $dependsOn = [],
        public array $foreignKeys = [],
    ) {
    }
}
