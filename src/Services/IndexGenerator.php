<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use DevSite\LaravelMigrationSearcher\Contracts\Writers\FileWriter;
use DevSite\LaravelMigrationSearcher\Contracts\Services\IndexDataBuilder as IndexDataBuilderContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\IndexGenerator as IndexGeneratorContract;
use DevSite\LaravelMigrationSearcher\Contracts\Renderers\Renderer;

class IndexGenerator implements IndexGeneratorContract
{
    public function __construct(
        protected string $outputPath,
        protected Renderer $renderer,
        protected IndexDataBuilderContract $dataBuilder,
        protected FileWriter $writer,
    ) {
        $this->outputPath = rtrim($outputPath, '/');
    }

    /**
     * @param list<array<string, mixed>> $migrations
     * @return array<string, string>
     */
    public function generateAll(array $migrations): array
    {
        $this->writer->ensureDirectory($this->outputPath);

        $ext = $this->renderer->getFileExtension();
        $generated = [];

        $fullData = $this->dataBuilder->buildFullIndex($migrations);
        $generated['full'] = $this->writeIndex("index-full.{$ext}", $this->renderer->renderFullIndex($fullData));

        $byTypeData = $this->dataBuilder->buildByTypeIndex($migrations);
        $generated['by_type'] = $this->writeIndex("index-by-type.{$ext}", $this->renderer->renderByTypeIndex($byTypeData));

        $byTableData = $this->dataBuilder->buildByTableIndex($migrations);
        $generated['by_table'] = $this->writeIndex("index-by-table.{$ext}", $this->renderer->renderByTableIndex($byTableData));

        $byOperationData = $this->dataBuilder->buildByOperationIndex($migrations);
        $generated['by_operation'] = $this->writeIndex("index-by-operation.{$ext}", $this->renderer->renderByOperationIndex($byOperationData));

        $statsData = $this->dataBuilder->buildStats($migrations);
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
