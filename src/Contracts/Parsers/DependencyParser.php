<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Parsers;

interface DependencyParser extends ContentParser
{
    /** @return array{requires: string[], depends_on: string[], foreign_keys: list<array{column: string, references: string, on_table: string}>} */
    public function parse(string $content): array;
}
