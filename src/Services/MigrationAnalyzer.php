<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use DevSite\LaravelMigrationSearcher\Contracts\MigrationAnalyzer as MigrationAnalyzerContract;
use DevSite\LaravelMigrationSearcher\DTOs\MigrationAnalysisResult;
use DevSite\LaravelMigrationSearcher\Parsers\DdlParser;
use DevSite\LaravelMigrationSearcher\Parsers\DependencyParser;
use DevSite\LaravelMigrationSearcher\Parsers\DmlParser;
use DevSite\LaravelMigrationSearcher\Support\MigrationFileInfo;
use DevSite\LaravelMigrationSearcher\Parsers\RawSqlParser;
use DevSite\LaravelMigrationSearcher\Parsers\TableDetector;
use Illuminate\Support\Facades\File;

class MigrationAnalyzer implements MigrationAnalyzerContract
{
    public function __construct(
        protected MigrationFileInfo $migrationFileInfo = new MigrationFileInfo(),
        protected TableDetector $tableDetector = new TableDetector(),
        protected DdlParser $ddlParser = new DdlParser(),
        protected DmlParser $dmlParser = new DmlParser(),
        protected RawSqlParser $rawSqlParser = new RawSqlParser(),
        protected DependencyParser $dependencyParser = new DependencyParser(),
        protected ComplexityCalculator $complexityCalculator = new ComplexityCalculator(),
    ) {
    }

    public function analyze(string $filepath, string $type): MigrationAnalysisResult
    {
        $maxFileSize = config('migration-searcher.max_file_size', 5242880);
        $fileSize = File::size($filepath);

        if ($fileSize > $maxFileSize) {
            throw new \RuntimeException(
                "File exceeds maximum allowed size ({$fileSize} bytes > {$maxFileSize} bytes)"
            );
        }

        $filename = basename($filepath);
        $content = File::get($filepath);

        $tables = $this->tableDetector->parse($content);
        $ddlOperations = $this->ddlParser->parse($content);
        $dmlOperations = $this->dmlParser->parse($content);
        $rawSql = $this->rawSqlParser->parse($content);
        $foreignKeys = $this->ddlParser->extractForeignKeys($content);

        return new MigrationAnalysisResult(
            $filename,
            $filepath,
            $this->migrationFileInfo->getRelativePath($filepath),
            $type,
            $this->migrationFileInfo->extractTimestamp($filename),
            $this->migrationFileInfo->extractMigrationName($filename),
            $tables,
            $ddlOperations,
            $dmlOperations,
            $rawSql,
            $this->dependencyParser->parse($content),
            $this->ddlParser->extractColumns($content),
            $this->ddlParser->extractIndexes($content),
            $foreignKeys,
            $this->ddlParser->extractMethodsUsed($content),
            $this->dmlParser->hasDataModifications($content),
            $this->complexityCalculator->calculate(
                $tables,
                $ddlOperations,
                $dmlOperations,
                $rawSql,
                $foreignKeys,
            ),
        );
    }
}
