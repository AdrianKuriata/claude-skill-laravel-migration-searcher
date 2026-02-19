<?php

namespace DevSite\LaravelMigrationSearcher\Contracts;

interface MigrationAnalyzerInterface
{
    public function analyze(string $filepath, string $type): array;
}
