<?php

namespace DevSite\LaravelMigrationSearcher\Contracts;

interface ContentParser
{
    public function parse(string $content): array;
}
