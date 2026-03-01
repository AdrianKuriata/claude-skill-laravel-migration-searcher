<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Parsers;

use DevSite\LaravelMigrationSearcher\DTOs\DmlOperation;

interface DmlParser extends ContentParser
{
    /** @return list<DmlOperation> */
    public function parse(string $content): array;

    public function hasDataModifications(string $content): bool;
}
