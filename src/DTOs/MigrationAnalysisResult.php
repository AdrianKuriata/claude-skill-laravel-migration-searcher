<?php

namespace DevSite\LaravelMigrationSearcher\DTOs;

final readonly class MigrationAnalysisResult extends BaseDTO
{
    public function __construct(
        public string $filename,
        public string $filepath,
        public string $relativePath,
        public string $type,
        public string $timestamp,
        public string $name,
        public array $tables,
        public array $ddlOperations,
        public array $dmlOperations,
        public array $rawSql,
        public array $dependencies,
        public array $columns,
        public array $indexes,
        public array $foreignKeys,
        public array $methodsUsed,
        public bool $hasDataModifications,
        public int $complexity,
    ) {}
}
