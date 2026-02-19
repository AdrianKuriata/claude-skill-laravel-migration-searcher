<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use DevSite\LaravelMigrationSearcher\Contracts\MigrationAnalyzerInterface;
use DevSite\LaravelMigrationSearcher\Services\Parsers\DdlParser;
use DevSite\LaravelMigrationSearcher\Services\Parsers\DependencyParser;
use DevSite\LaravelMigrationSearcher\Services\Parsers\DmlParser;
use DevSite\LaravelMigrationSearcher\Services\Parsers\FileNameParser;
use DevSite\LaravelMigrationSearcher\Services\Parsers\RawSqlParser;
use DevSite\LaravelMigrationSearcher\Services\Parsers\TableDetector;
use Illuminate\Support\Facades\File;

class MigrationAnalyzer implements MigrationAnalyzerInterface
{
    public function __construct(
        protected FileNameParser $fileNameParser = new FileNameParser(),
        protected TableDetector $tableDetector = new TableDetector(),
        protected DdlParser $ddlParser = new DdlParser(),
        protected DmlParser $dmlParser = new DmlParser(),
        protected RawSqlParser $rawSqlParser = new RawSqlParser(),
        protected DependencyParser $dependencyParser = new DependencyParser(),
        protected ComplexityCalculator $complexityCalculator = new ComplexityCalculator(),
    ) {
    }

    public function analyze(string $filepath, string $type): array
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

        $result = [
            'filename' => $filename,
            'filepath' => $filepath,
            'relative_path' => $this->fileNameParser->getRelativePath($filepath),
            'type' => $type,
            'timestamp' => $this->fileNameParser->extractTimestamp($filename),
            'name' => $this->fileNameParser->extractMigrationName($filename),
            'tables' => $this->tableDetector->parse($content),
            'ddl_operations' => $this->ddlParser->parse($content),
            'dml_operations' => $this->dmlParser->parse($content),
            'raw_sql' => $this->rawSqlParser->parse($content),
            'dependencies' => $this->dependencyParser->parse($content),
            'columns' => $this->ddlParser->extractColumns($content),
            'indexes' => $this->ddlParser->extractIndexes($content),
            'foreign_keys' => $this->ddlParser->extractForeignKeys($content),
            'methods_used' => $this->ddlParser->extractMethodsUsed($content),
            'has_data_modifications' => $this->dmlParser->hasDataModifications($content),
        ];

        $result['complexity'] = $this->complexityCalculator->calculate($result);

        return $result;
    }
}
