<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Parsers;

use DevSite\LaravelMigrationSearcher\DTOs\TableInfo;

interface TableDetector extends ContentParser
{
    /** @return array<string, TableInfo> */
    public function parse(string $content): array;
}
