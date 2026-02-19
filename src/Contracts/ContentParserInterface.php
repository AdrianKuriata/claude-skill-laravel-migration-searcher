<?php

namespace DevSite\LaravelMigrationSearcher\Contracts;

interface ContentParserInterface
{
    public function parse(string $content): array;
}
