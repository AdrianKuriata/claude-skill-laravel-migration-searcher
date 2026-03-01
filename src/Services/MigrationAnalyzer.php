<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use DevSite\LaravelMigrationSearcher\Contracts\Services\ComplexityCalculator as ComplexityCalculatorContract;
use DevSite\LaravelMigrationSearcher\Contracts\Parsers\DdlParser as DdlParserContract;
use DevSite\LaravelMigrationSearcher\Contracts\Parsers\DependencyParser as DependencyParserContract;
use DevSite\LaravelMigrationSearcher\Contracts\Parsers\DmlParser as DmlParserContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\MigrationAnalyzer as MigrationAnalyzerContract;
use DevSite\LaravelMigrationSearcher\Contracts\Parsers\RawSqlParser as RawSqlParserContract;
use DevSite\LaravelMigrationSearcher\Contracts\Parsers\TableDetector as TableDetectorContract;
use DevSite\LaravelMigrationSearcher\DTOs\DependencyInfo;
use DevSite\LaravelMigrationSearcher\DTOs\MigrationAnalysisResult;
use DevSite\LaravelMigrationSearcher\Exceptions\FileSizeLimitExceededException;
use DevSite\LaravelMigrationSearcher\Contracts\Support\MigrationFileInfo as MigrationFileInfoContract;
use DevSite\LaravelMigrationSearcher\ValueObjects\MigrationTimestamp;

class MigrationAnalyzer implements MigrationAnalyzerContract
{
    public function __construct(
        protected MigrationFileInfoContract $migrationFileInfo,
        protected TableDetectorContract $tableDetector,
        protected DdlParserContract $ddlParser,
        protected DmlParserContract $dmlParser,
        protected RawSqlParserContract $rawSqlParser,
        protected DependencyParserContract $dependencyParser,
        protected ComplexityCalculatorContract $complexityCalculator,
        protected int $maxFileSize = 5242880,
    ) {
    }

    public function analyze(string $filepath, string $type): MigrationAnalysisResult
    {
        $fileSize = $this->migrationFileInfo->getFileSize($filepath);

        if ($fileSize > $this->maxFileSize) {
            throw FileSizeLimitExceededException::create($fileSize, $this->maxFileSize);
        }

        $filename = basename($filepath);
        $content = $this->migrationFileInfo->getContents($filepath);

        $tables = $this->tableDetector->parse($content);
        $ddlOperations = $this->ddlParser->parse($content);
        $dmlOperations = $this->dmlParser->parse($content);
        $rawSql = $this->rawSqlParser->parse($content);
        $foreignKeys = $this->ddlParser->extractForeignKeys($content);
        $dependencies = $this->dependencyParser->parse($content);

        return new MigrationAnalysisResult(
            $filename,
            $filepath,
            $this->migrationFileInfo->getRelativePath($filepath),
            $type,
            new MigrationTimestamp($this->migrationFileInfo->extractTimestamp($filename)),
            $this->migrationFileInfo->extractMigrationName($filename),
            $tables,
            $ddlOperations,
            $dmlOperations,
            $rawSql,
            new DependencyInfo(
                $dependencies['requires'] ?? [],
                $dependencies['depends_on'] ?? [],
                $dependencies['foreign_keys'] ?? [],
            ),
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
