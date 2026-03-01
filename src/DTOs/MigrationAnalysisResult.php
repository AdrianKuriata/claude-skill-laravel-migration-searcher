<?php

namespace DevSite\LaravelMigrationSearcher\DTOs;

use DevSite\LaravelMigrationSearcher\ValueObjects\ComplexityScore;
use DevSite\LaravelMigrationSearcher\ValueObjects\MigrationTimestamp;

final readonly class MigrationAnalysisResult extends BaseDTO
{
    /**
     * @param string $filename
     * @param string $filepath
     * @param string $relativePath
     * @param string $type
     * @param MigrationTimestamp $timestamp
     * @param string $name
     * @param array<string, TableInfo> $tables
     * @param DdlOperation[] $ddlOperations
     * @param DmlOperation[] $dmlOperations
     * @param RawSqlStatement[] $rawSql
     * @param DependencyInfo $dependencies
     * @param array<string, ColumnDefinition> $columns
     * @param IndexDefinition[] $indexes
     * @param ForeignKeyDefinition[] $foreignKeys
     * @param string[] $methodsUsed
     * @param bool $hasDataModifications
     * @param ComplexityScore $complexity
     */
    public function __construct(
        public string $filename,
        public string $filepath,
        public string $relativePath,
        public string $type,
        public MigrationTimestamp $timestamp,
        public string $name,
        public array $tables,
        public array $ddlOperations,
        public array $dmlOperations,
        public array $rawSql,
        public DependencyInfo $dependencies,
        public array $columns,
        public array $indexes,
        public array $foreignKeys,
        public array $methodsUsed,
        public bool $hasDataModifications,
        public ComplexityScore $complexity,
    ) {
    }
}
