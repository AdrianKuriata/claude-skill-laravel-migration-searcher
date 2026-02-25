<?php

namespace DevSite\LaravelMigrationSearcher\Contracts;

use DevSite\LaravelMigrationSearcher\DTOs\MigrationAnalysisResult;

interface MigrationAnalyzerInterface
{
    public function analyze(string $filepath, string $type): MigrationAnalysisResult;
}
