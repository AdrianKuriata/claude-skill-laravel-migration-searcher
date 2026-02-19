<?php

namespace DevSite\LaravelMigrationSearcher\Services;

class ComplexityCalculator
{
    public function calculate(array $result): int
    {
        $score = 0;

        $score += count($result['tables'] ?? []);
        $score += count($result['ddl_operations'] ?? []) * 0.5;
        $score += count($result['dml_operations'] ?? []) * 2;
        $score += count($result['raw_sql'] ?? []) * 3;
        $score += count($result['foreign_keys'] ?? []) * 1.5;

        return min(10, max(1, (int) round($score)));
    }
}
