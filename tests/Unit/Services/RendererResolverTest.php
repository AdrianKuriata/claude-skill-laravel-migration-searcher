<?php

namespace Tests\Unit\Services;

use DevSite\LaravelMigrationSearcher\Renderers\JsonRenderer;
use DevSite\LaravelMigrationSearcher\Renderers\MarkdownRenderer;
use DevSite\LaravelMigrationSearcher\Services\RendererResolver;
use Tests\TestCase;

class RendererResolverTest extends TestCase
{
    protected RendererResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new RendererResolver();
    }

    public function testResolvesMarkdownByDefault(): void
    {
        $renderer = $this->resolver->resolve('markdown');
        $this->assertInstanceOf(MarkdownRenderer::class, $renderer);
    }

    public function testResolvesJson(): void
    {
        $renderer = $this->resolver->resolve('json');
        $this->assertInstanceOf(JsonRenderer::class, $renderer);
    }

    public function testReturnsNullForInvalidFormat(): void
    {
        $this->assertNull($this->resolver->resolve('xml'));
    }

    public function testConfigOverridesDefaultFormat(): void
    {
        config(['migration-searcher.formats' => [
            'markdown' => JsonRenderer::class,
        ]]);

        $renderer = $this->resolver->resolve('markdown');
        $this->assertInstanceOf(JsonRenderer::class, $renderer);
    }

    public function testConfigAddsCustomFormat(): void
    {
        config(['migration-searcher.formats' => [
            'custom' => MarkdownRenderer::class,
        ]]);

        $renderer = $this->resolver->resolve('custom');
        $this->assertInstanceOf(MarkdownRenderer::class, $renderer);
    }

    public function testAvailableFormatsReturnsDefaults(): void
    {
        config(['migration-searcher.formats' => []]);

        $formats = $this->resolver->availableFormats();
        $this->assertSame(['markdown', 'json'], $formats);
    }

    public function testAvailableFormatsIncludesCustom(): void
    {
        config(['migration-searcher.formats' => [
            'yaml' => MarkdownRenderer::class,
        ]]);

        $formats = $this->resolver->availableFormats();
        $this->assertContains('markdown', $formats);
        $this->assertContains('json', $formats);
        $this->assertContains('yaml', $formats);
    }
}
