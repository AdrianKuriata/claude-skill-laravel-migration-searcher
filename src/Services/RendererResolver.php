<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use DevSite\LaravelMigrationSearcher\Contracts\Renderer;
use DevSite\LaravelMigrationSearcher\Contracts\RendererResolver as RendererResolverContract;
use DevSite\LaravelMigrationSearcher\Renderers\JsonRenderer;
use DevSite\LaravelMigrationSearcher\Renderers\MarkdownRenderer;

class RendererResolver implements RendererResolverContract
{
    /** @var array<string, class-string<Renderer>> */
    protected array $defaultFormats = [
        'markdown' => MarkdownRenderer::class,
        'json' => JsonRenderer::class,
    ];

    public function resolve(string $format): ?Renderer
    {
        $formats = array_merge($this->defaultFormats, config('migration-searcher.formats', []));
        $class = $formats[$format] ?? null;

        return $class ? app($class) : null;
    }

    /** @return string[] */
    public function availableFormats(): array
    {
        return array_keys(array_merge($this->defaultFormats, config('migration-searcher.formats', [])));
    }
}
