<?php

namespace DevSite\LaravelMigrationSearcher\DTOs;

final readonly class DependencyInfo extends BaseDTO
{
    /**
     * @param string[] $requires
     * @param string[] $dependsOn
     * @param list<array{column: string, references: string, on_table: string}> $foreignKeys
     */
    public function __construct(
        public array $requires = [],
        public array $dependsOn = [],
        public array $foreignKeys = [],
    ) {
    }
}
