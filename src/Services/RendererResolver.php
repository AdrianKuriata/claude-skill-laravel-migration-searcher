<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use DevSite\LaravelMigrationSearcher\Contracts\Renderers\Renderer;
use DevSite\LaravelMigrationSearcher\Contracts\Renderers\RendererResolver as RendererResolverContract;
use Illuminate\Contracts\Container\Container;

class RendererResolver implements RendererResolverContract
{
    /** @var array<string, string> */
    protected array $formats;

    /** @param array<string, mixed> $formats */
    public function __construct(
        array $formats,
        protected Container $container,
    ) {
        /** @var array<string, string> $filtered */
        $filtered = array_filter($formats, fn (mixed $value): bool => is_string($value));
        $this->formats = $filtered;
    }

    public function resolve(string $format): ?Renderer
    {
        $class = $this->formats[$format] ?? null;

        if ($class === null || !class_exists($class) || !is_subclass_of($class, Renderer::class)) {
            return null;
        }

        /** @var Renderer */
        return $this->container->make($class);
    }

    /** @return string[] */
    public function availableFormats(): array
    {
        return array_keys($this->formats);
    }
}
