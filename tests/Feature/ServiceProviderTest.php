<?php

namespace Tests\Feature;

use DevSite\LaravelMigrationSearcher\Commands\IndexMigrationsCommand;
use DevSite\LaravelMigrationSearcher\Contracts\FileWriterInterface;
use DevSite\LaravelMigrationSearcher\Contracts\IndexDataBuilderInterface;
use DevSite\LaravelMigrationSearcher\Contracts\IndexGeneratorInterface;
use DevSite\LaravelMigrationSearcher\Contracts\MigrationAnalyzerInterface;
use DevSite\LaravelMigrationSearcher\Contracts\RendererInterface;
use DevSite\LaravelMigrationSearcher\Services\IndexDataBuilder;
use DevSite\LaravelMigrationSearcher\Services\IndexGenerator;
use DevSite\LaravelMigrationSearcher\Services\MigrationAnalyzer;
use DevSite\LaravelMigrationSearcher\Services\Renderers\JsonRenderer;
use DevSite\LaravelMigrationSearcher\Services\Renderers\MarkdownRenderer;
use DevSite\LaravelMigrationSearcher\Services\Writers\IndexFileWriter;
use Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function testConfigIsMerged(): void
    {
        $config = config('migration-searcher');

        $this->assertNotNull($config);
        $this->assertArrayHasKey('output_path', $config);
        $this->assertArrayHasKey('migration_types', $config);
        $this->assertArrayHasKey('skill_template_path', $config);
        $this->assertArrayHasKey('default_format', $config);
    }

    public function testDefaultFormatIsMarkdown(): void
    {
        $this->assertSame('markdown', config('migration-searcher.default_format'));
    }

    public function testCommandIsRegistered(): void
    {
        $commands = $this->app['Illuminate\Contracts\Console\Kernel']->all();

        $this->assertArrayHasKey('migrations:index', $commands);
        $this->assertInstanceOf(IndexMigrationsCommand::class, $commands['migrations:index']);
    }

    public function testBindsMigrationAnalyzerInterface(): void
    {
        $instance = $this->app->make(MigrationAnalyzerInterface::class);
        $this->assertInstanceOf(MigrationAnalyzer::class, $instance);
    }

    public function testBindsFileWriterInterface(): void
    {
        $instance = $this->app->make(FileWriterInterface::class);
        $this->assertInstanceOf(IndexFileWriter::class, $instance);
    }

    public function testBindsIndexGeneratorInterface(): void
    {
        $instance = $this->app->make(IndexGeneratorInterface::class);
        $this->assertInstanceOf(IndexGenerator::class, $instance);
    }

    public function testBindsIndexDataBuilderInterface(): void
    {
        $instance = $this->app->make(IndexDataBuilderInterface::class);
        $this->assertInstanceOf(IndexDataBuilder::class, $instance);
    }

    public function testBindsRendererInterface(): void
    {
        $instance = $this->app->make(RendererInterface::class);
        $this->assertInstanceOf(MarkdownRenderer::class, $instance);
    }

    public function testBindsRendererInterfaceRespectsConfig(): void
    {
        $this->app['config']->set('migration-searcher.default_format', 'json');

        $instance = $this->app->make(RendererInterface::class);
        $this->assertInstanceOf(JsonRenderer::class, $instance);
    }
}
