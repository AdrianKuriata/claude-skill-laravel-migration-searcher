<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Renderers;

interface RendererResolver
{
    public function resolve(string $format): ?Renderer;

    /** @return string[] */
    public function availableFormats(): array;
}
