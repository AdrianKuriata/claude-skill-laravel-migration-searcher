<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use DevSite\LaravelMigrationSearcher\Contracts\Writers\FileWriter;
use DevSite\LaravelMigrationSearcher\Contracts\Services\IndexDataBuilder as IndexDataBuilderContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\IndexGenerator as IndexGeneratorContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\IndexGeneratorFactory as IndexGeneratorFactoryContract;
use DevSite\LaravelMigrationSearcher\Contracts\Renderers\Renderer;

class IndexGeneratorFactory implements IndexGeneratorFactoryContract
{
    public function __construct(
        protected IndexDataBuilderContract $dataBuilder,
        protected FileWriter $fileWriter,
    ) {
    }

    public function create(string $outputPath, Renderer $renderer): IndexGeneratorContract
    {
        return new IndexGenerator($outputPath, $renderer, $this->dataBuilder, $this->fileWriter);
    }
}
