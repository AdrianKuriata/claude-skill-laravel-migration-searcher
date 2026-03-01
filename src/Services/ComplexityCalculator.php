<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use DevSite\LaravelMigrationSearcher\Contracts\Services\ComplexityCalculator as ComplexityCalculatorContract;
use DevSite\LaravelMigrationSearcher\DTOs\DdlOperation;
use DevSite\LaravelMigrationSearcher\DTOs\DmlOperation;
use DevSite\LaravelMigrationSearcher\DTOs\ForeignKeyDefinition;
use DevSite\LaravelMigrationSearcher\DTOs\RawSqlStatement;
use DevSite\LaravelMigrationSearcher\DTOs\TableInfo;
use DevSite\LaravelMigrationSearcher\ValueObjects\ComplexityScore;

class ComplexityCalculator implements ComplexityCalculatorContract
{
    /**
     * @param array<string, TableInfo> $tables
     * @param list<DdlOperation> $ddlOperations
     * @param list<DmlOperation> $dmlOperations
     * @param list<RawSqlStatement> $rawSql
     * @param list<ForeignKeyDefinition> $foreignKeys
     */
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
