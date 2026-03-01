<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Services;

use DevSite\LaravelMigrationSearcher\DTOs\MigrationAnalysisResult;

interface MigrationAnalyzer
{
    public function analyze(string $filepath, string $type): MigrationAnalysisResult;
}
