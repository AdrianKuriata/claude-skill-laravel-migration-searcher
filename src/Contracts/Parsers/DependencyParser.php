<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Parsers;

interface DependencyParser extends ContentParser
{
    public function parse(string $content): array;
}
