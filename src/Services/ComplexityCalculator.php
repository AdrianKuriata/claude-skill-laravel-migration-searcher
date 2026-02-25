<?php

namespace DevSite\LaravelMigrationSearcher\Services;

class ComplexityCalculator
{
    public function calculate(
        array $tables,
        array $ddlOperations,
        array $dmlOperations,
        array $rawSql,
        array $foreignKeys,
    ): int {
        $score = 0;

        $score += count($tables);
        $score += count($ddlOperations) * 0.5;
        $score += count($dmlOperations) * 2;
        $score += count($rawSql) * 3;
        $score += count($foreignKeys) * 1.5;

        return min(10, max(1, (int) round($score)));
    }
}
