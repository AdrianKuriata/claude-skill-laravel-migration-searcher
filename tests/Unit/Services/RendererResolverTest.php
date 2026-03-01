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
        $this->resolver = new RendererResolver([
            'markdown' => MarkdownRenderer::class,
            'json' => JsonRenderer::class,
        ], $this->app);
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
        $resolver = new RendererResolver([
            'markdown' => JsonRenderer::class,
            'json' => JsonRenderer::class,
        ], $this->app);

        $renderer = $resolver->resolve('markdown');
        $this->assertInstanceOf(JsonRenderer::class, $renderer);
    }

    public function testConfigAddsCustomFormat(): void
    {
        $resolver = new RendererResolver([
            'markdown' => MarkdownRenderer::class,
            'json' => JsonRenderer::class,
            'custom' => MarkdownRenderer::class,
        ], $this->app);

        $renderer = $resolver->resolve('custom');
        $this->assertInstanceOf(MarkdownRenderer::class, $renderer);
    }

    public function testAvailableFormatsReturnsDefaults(): void
    {
        $formats = $this->resolver->availableFormats();
        $this->assertSame(['markdown', 'json'], $formats);
    }

    public function testAvailableFormatsIncludesCustom(): void
    {
        $resolver = new RendererResolver([
            'markdown' => MarkdownRenderer::class,
            'json' => JsonRenderer::class,
            'yaml' => MarkdownRenderer::class,
        ], $this->app);

        $formats = $resolver->availableFormats();
        $this->assertContains('markdown', $formats);
        $this->assertContains('json', $formats);
        $this->assertContains('yaml', $formats);
    }

    public function testReturnsNullForNonRendererClass(): void
    {
        $resolver = new RendererResolver([
            'bad' => \stdClass::class,
        ], $this->app);

        $this->assertNull($resolver->resolve('bad'));
    }

    public function testReturnsNullForNonExistentClass(): void
    {
        $resolver = new RendererResolver([
            'missing' => 'App\\NonExistent\\Renderer',
        ], $this->app);

        $this->assertNull($resolver->resolve('missing'));
    }

    public function testReturnsNullForNonStringClassValue(): void
    {
        $resolver = new RendererResolver([
            'invalid' => 12345,
        ], $this->app);

        $this->assertNull($resolver->resolve('invalid'));
        $this->assertNotContains('invalid', $resolver->availableFormats());
    }

    public function testConstructorFiltersMixedNonStringValues(): void
    {
        $resolver = new RendererResolver([
            'markdown' => MarkdownRenderer::class,
            'int_val' => 123,
            'null_val' => null,
            'array_val' => ['bad'],
            'json' => JsonRenderer::class,
        ], $this->app);

        $formats = $resolver->availableFormats();
        $this->assertSame(['markdown', 'json'], $formats);
    }

    public function testValidatesClassBeforeInstantiation(): void
    {
        $resolver = new RendererResolver([
            'bad' => \stdClass::class,
        ], $this->app);

        $this->assertNull($resolver->resolve('bad'));
    }
}
