<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Parsers;

interface ContentParser
{
    public function parse(string $content): array;
}
