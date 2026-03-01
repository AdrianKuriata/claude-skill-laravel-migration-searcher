<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use DevSite\LaravelMigrationSearcher\Contracts\Renderers\Renderer;
use DevSite\LaravelMigrationSearcher\Contracts\Renderers\RendererResolver as RendererResolverContract;
use Illuminate\Contracts\Container\Container;

class RendererResolver implements RendererResolverContract
{
    /** @param array<string, class-string<Renderer>> $formats */
    public function __construct(
        protected array $formats,
        protected Container $container,
    ) {
        $this->formats = array_filter($formats, fn ($class) => is_string($class));
    }

    public function resolve(string $format): ?Renderer
    {
        $class = $this->formats[$format] ?? null;

        if (!is_string($class) || !class_exists($class) || !is_subclass_of($class, Renderer::class)) {
            return null;
        }

        return $this->container->make($class);
    }

    /** @return string[] */
    public function availableFormats(): array
    {
        return array_keys($this->formats);
    }
}
