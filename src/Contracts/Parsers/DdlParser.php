<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Parsers;

use DevSite\LaravelMigrationSearcher\DTOs\ColumnDefinition;
use DevSite\LaravelMigrationSearcher\DTOs\DdlOperation;
use DevSite\LaravelMigrationSearcher\DTOs\ForeignKeyDefinition;
use DevSite\LaravelMigrationSearcher\DTOs\IndexDefinition;

interface DdlParser extends ContentParser
{
    /** @return list<DdlOperation> */
    public function parse(string $content): array;

    /** @return array<string, ColumnDefinition> */
    public function extractColumns(string $content): array;

    /** @return list<IndexDefinition> */
    public function extractIndexes(string $content): array;

    /** @return list<ForeignKeyDefinition> */
    public function extractForeignKeys(string $content): array;

    /** @return list<string> */
    public function extractMethodsUsed(string $content): array;
}
