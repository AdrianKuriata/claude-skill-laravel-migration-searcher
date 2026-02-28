<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use DevSite\LaravelMigrationSearcher\Contracts\FileWriter;
use DevSite\LaravelMigrationSearcher\Contracts\IndexDataBuilder as IndexDataBuilderContract;
use DevSite\LaravelMigrationSearcher\Contracts\IndexGenerator as IndexGeneratorContract;
use DevSite\LaravelMigrationSearcher\Contracts\Renderer;
use DevSite\LaravelMigrationSearcher\Renderers\MarkdownRenderer;
use DevSite\LaravelMigrationSearcher\Writers\IndexFileWriter;

class IndexGenerator implements IndexGeneratorContract
{
    protected array $migrations = [];
    protected string $outputPath;
    protected Renderer $renderer;
    protected IndexDataBuilderContract $dataBuilder;
    protected FileWriter $writer;

    public function __construct(
        string $outputPath,
        ?Renderer $renderer = null,
        ?IndexDataBuilderContract $dataBuilder = null,
        ?FileWriter $writer = null,
    ) {
        $this->outputPath = rtrim($outputPath, '/');
        $this->renderer = $renderer ?? new MarkdownRenderer();
        $this->dataBuilder = $dataBuilder ?? new IndexDataBuilder();
        $this->writer = $writer ?? new IndexFileWriter();
    }

    public function setMigrations(array $migrations): void
    {
        $this->migrations = $migrations;
    }

    public function generateAll(): array
    {
        $this->writer->ensureDirectory($this->outputPath);

        $ext = $this->renderer->getFileExtension();
        $generated = [];

        $fullData = $this->dataBuilder->buildFullIndex($this->migrations);
        $generated['full'] = $this->writeIndex("index-full.{$ext}", $this->renderer->renderFullIndex($fullData));

        $byTypeData = $this->dataBuilder->buildByTypeIndex($this->migrations);
        $generated['by_type'] = $this->writeIndex("index-by-type.{$ext}", $this->renderer->renderByTypeIndex($byTypeData));

        $byTableData = $this->dataBuilder->buildByTableIndex($this->migrations);
        $generated['by_table'] = $this->writeIndex("index-by-table.{$ext}", $this->renderer->renderByTableIndex($byTableData));

        $byOperationData = $this->dataBuilder->buildByOperationIndex($this->migrations);
        $generated['by_operation'] = $this->writeIndex("index-by-operation.{$ext}", $this->renderer->renderByOperationIndex($byOperationData));

        $statsData = $this->dataBuilder->buildStats($this->migrations);
        $generated['stats'] = $this->writeIndex("stats.{$ext}", $this->renderer->renderStats($statsData));

        return $generated;
    }

    protected function writeIndex(string $filename, string $content): string
    {
        $filepath = $this->outputPath . '/' . $filename;
        $this->writer->write($filepath, $content);

        return $filepath;
    }
}
