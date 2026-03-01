<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Parsers;

interface ContentParser
{
    /** @return array<int|string, mixed> */
    public function parse(string $content): array;
}
