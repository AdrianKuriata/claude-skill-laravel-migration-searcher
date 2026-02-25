<?php

namespace Tests\Feature;

use DevSite\LaravelMigrationSearcher\Console\Commands\IndexMigrationsCommand;
use DevSite\LaravelMigrationSearcher\Contracts\FileWriter;
use DevSite\LaravelMigrationSearcher\Contracts\IndexDataBuilder as IndexDataBuilderContract;
use DevSite\LaravelMigrationSearcher\Contracts\IndexGenerator as IndexGeneratorContract;
use DevSite\LaravelMigrationSearcher\Contracts\MigrationAnalyzer as MigrationAnalyzerContract;
use DevSite\LaravelMigrationSearcher\Contracts\Renderer;
use DevSite\LaravelMigrationSearcher\Renderers\JsonRenderer;
use DevSite\LaravelMigrationSearcher\Renderers\MarkdownRenderer;
use DevSite\LaravelMigrationSearcher\Services\IndexDataBuilder;
use DevSite\LaravelMigrationSearcher\Services\IndexGenerator;
use DevSite\LaravelMigrationSearcher\Services\MigrationAnalyzer;
use DevSite\LaravelMigrationSearcher\Writers\IndexFileWriter;
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

    public function testBindsMigrationAnalyzerContract(): void
    {
        $instance = $this->app->make(MigrationAnalyzerContract::class);
        $this->assertInstanceOf(MigrationAnalyzer::class, $instance);
    }

    public function testBindsFileWriter(): void
    {
        $instance = $this->app->make(FileWriter::class);
        $this->assertInstanceOf(IndexFileWriter::class, $instance);
    }

    public function testBindsIndexGeneratorContract(): void
    {
        $instance = $this->app->make(IndexGeneratorContract::class);
        $this->assertInstanceOf(IndexGenerator::class, $instance);
    }

    public function testBindsIndexDataBuilderContract(): void
    {
        $instance = $this->app->make(IndexDataBuilderContract::class);
        $this->assertInstanceOf(IndexDataBuilder::class, $instance);
    }

    public function testBindsRenderer(): void
    {
        $instance = $this->app->make(Renderer::class);
        $this->assertInstanceOf(MarkdownRenderer::class, $instance);
    }

    public function testBindsRendererRespectsConfig(): void
    {
        $this->app['config']->set('migration-searcher.default_format', 'json');

        $instance = $this->app->make(Renderer::class);
        $this->assertInstanceOf(JsonRenderer::class, $instance);
    }
}
