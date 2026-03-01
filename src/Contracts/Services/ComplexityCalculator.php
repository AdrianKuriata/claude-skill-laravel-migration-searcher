<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Services;

use DevSite\LaravelMigrationSearcher\ValueObjects\ComplexityScore;

interface ComplexityCalculator
{
    public function calculate(
        array $tables,
        array $ddlOperations,
        array $dmlOperations,
        array $rawSql,
        array $foreignKeys,
    ): ComplexityScore;
}
