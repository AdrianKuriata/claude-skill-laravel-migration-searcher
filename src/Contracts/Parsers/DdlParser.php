<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Parsers;

use DevSite\LaravelMigrationSearcher\DTOs\ColumnDefinition;
use DevSite\LaravelMigrationSearcher\DTOs\DdlOperation;
use DevSite\LaravelMigrationSearcher\DTOs\ForeignKeyDefinition;
use DevSite\LaravelMigrationSearcher\DTOs\IndexDefinition;

interface DdlParser extends ContentParser
{
    /** @return DdlOperation[] */
    public function parse(string $content): array;

    /** @return array<string, ColumnDefinition> */
    public function extractColumns(string $content): array;

    /** @return IndexDefinition[] */
    public function extractIndexes(string $content): array;

    /** @return ForeignKeyDefinition[] */
    public function extractForeignKeys(string $content): array;

    /** @return string[] */
    public function extractMethodsUsed(string $content): array;
}
