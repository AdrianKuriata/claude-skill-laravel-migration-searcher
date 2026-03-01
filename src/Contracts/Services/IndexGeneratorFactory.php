<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Services;

use DevSite\LaravelMigrationSearcher\Contracts\Renderers\Renderer;

interface IndexGeneratorFactory
{
    public function create(string $outputPath, Renderer $renderer): IndexGenerator;
}
