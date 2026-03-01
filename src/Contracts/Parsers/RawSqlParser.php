<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Parsers;

use DevSite\LaravelMigrationSearcher\DTOs\RawSqlStatement;

interface RawSqlParser extends ContentParser
{
    /** @return RawSqlStatement[] */
    public function parse(string $content): array;
}
