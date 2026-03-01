<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use DevSite\LaravelMigrationSearcher\Contracts\Services\ComplexityCalculator as ComplexityCalculatorContract;
use DevSite\LaravelMigrationSearcher\ValueObjects\ComplexityScore;

class ComplexityCalculator implements ComplexityCalculatorContract
{
    public function calculate(
        array $tables,
        array $ddlOperations,
        array $dmlOperations,
        array $rawSql,
        array $foreignKeys,
    ): ComplexityScore {
        $score = 0;

        $score += count($tables);
        $score += count($ddlOperations) * 0.5;
        $score += count($dmlOperations) * 2;
        $score += count($rawSql) * 3;
        $score += count($foreignKeys) * 1.5;

        return new ComplexityScore(min(10, max(1, (int) round($score))));
    }
}
