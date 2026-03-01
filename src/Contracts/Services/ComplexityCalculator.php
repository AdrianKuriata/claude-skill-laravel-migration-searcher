<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Services;

use DevSite\LaravelMigrationSearcher\DTOs\DdlOperation;
use DevSite\LaravelMigrationSearcher\DTOs\DmlOperation;
use DevSite\LaravelMigrationSearcher\DTOs\ForeignKeyDefinition;
use DevSite\LaravelMigrationSearcher\DTOs\RawSqlStatement;
use DevSite\LaravelMigrationSearcher\DTOs\TableInfo;
use DevSite\LaravelMigrationSearcher\ValueObjects\ComplexityScore;

interface ComplexityCalculator
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
    ): ComplexityScore;
}
