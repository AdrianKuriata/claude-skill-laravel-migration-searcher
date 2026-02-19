<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use DevSite\LaravelMigrationSearcher\Contracts\FileWriterInterface;
use DevSite\LaravelMigrationSearcher\Contracts\IndexGeneratorInterface;
use DevSite\LaravelMigrationSearcher\Services\Renderers\MarkdownRenderer;
use DevSite\LaravelMigrationSearcher\Services\Writers\IndexFileWriter;

class IndexGenerator implements IndexGeneratorInterface
{
    protected array $migrations = [];
    protected string $outputPath;
    protected MarkdownRenderer $renderer;
    protected FileWriterInterface $writer;

    public function __construct(
        string $outputPath,
        ?MarkdownRenderer $renderer = null,
        ?FileWriterInterface $writer = null,
    ) {
        $this->outputPath = rtrim($outputPath, '/');
        $this->renderer = $renderer ?? new MarkdownRenderer();
        $this->writer = $writer ?? new IndexFileWriter();
    }

    public function setMigrations(array $migrations): void
    {
        $this->migrations = $migrations;
    }

    public function generateAll(): array
    {
        $this->writer->ensureDirectory($this->outputPath);

        $generated = [];

        $generated['full'] = $this->writeIndex('index-full.md', $this->renderer->renderFullIndex($this->migrations));
        $generated['by_type'] = $this->writeIndex('index-by-type.md', $this->renderer->renderByTypeIndex($this->migrations));
        $generated['by_table'] = $this->writeIndex('index-by-table.md', $this->renderer->renderByTableIndex($this->migrations));
        $generated['by_operation'] = $this->writeIndex('index-by-operation.md', $this->renderer->renderByOperationIndex($this->migrations));
        $generated['stats'] = $this->writeIndex('stats.json', $this->renderer->renderStats($this->migrations));

        return $generated;
    }

    protected function writeIndex(string $filename, string $content): string
    {
        $filepath = $this->outputPath . '/' . $filename;
        $this->writer->write($filepath, $content);

        return $filepath;
    }
}
