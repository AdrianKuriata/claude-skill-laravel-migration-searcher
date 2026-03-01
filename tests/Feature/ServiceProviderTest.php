<?php

namespace Tests\Feature;

use DevSite\LaravelMigrationSearcher\Console\Commands\IndexMigrationsCommand;
use DevSite\LaravelMigrationSearcher\Contracts\Services\ComplexityCalculator as ComplexityCalculatorContract;
use DevSite\LaravelMigrationSearcher\Contracts\Support\MigrationFileInfo as MigrationFileInfoContract;
use DevSite\LaravelMigrationSearcher\Contracts\Parsers\DdlParser as DdlParserContract;
use DevSite\LaravelMigrationSearcher\Contracts\Parsers\DependencyParser as DependencyParserContract;
use DevSite\LaravelMigrationSearcher\Contracts\Parsers\DmlParser as DmlParserContract;
use DevSite\LaravelMigrationSearcher\Contracts\Writers\FileWriter;
use DevSite\LaravelMigrationSearcher\Contracts\Services\IndexDataBuilder as IndexDataBuilderContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\IndexGenerator as IndexGeneratorContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\IndexGeneratorFactory as IndexGeneratorFactoryContract;
use DevSite\LaravelMigrationSearcher\Contracts\Renderers\MarkdownMigrationFormatter as MarkdownMigrationFormatterContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\MigrationAnalyzer as MigrationAnalyzerContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\PathValidator as PathValidatorContract;
use DevSite\LaravelMigrationSearcher\Contracts\Parsers\RawSqlParser as RawSqlParserContract;
use DevSite\LaravelMigrationSearcher\Contracts\Renderers\Renderer;
use DevSite\LaravelMigrationSearcher\Contracts\Renderers\RendererResolver as RendererResolverContract;
use DevSite\LaravelMigrationSearcher\Contracts\Parsers\TableDetector as TableDetectorContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\TextSanitizer;
use DevSite\LaravelMigrationSearcher\Parsers\DdlParser;
use DevSite\LaravelMigrationSearcher\Parsers\DependencyParser;
use DevSite\LaravelMigrationSearcher\Parsers\DmlParser;
use DevSite\LaravelMigrationSearcher\Parsers\RawSqlParser;
use DevSite\LaravelMigrationSearcher\Parsers\TableDetector;
use DevSite\LaravelMigrationSearcher\Renderers\JsonRenderer;
use DevSite\LaravelMigrationSearcher\Renderers\MarkdownMigrationFormatter;
use DevSite\LaravelMigrationSearcher\Renderers\MarkdownRenderer;
use DevSite\LaravelMigrationSearcher\Services\ComplexityCalculator;
use DevSite\LaravelMigrationSearcher\Services\IndexDataBuilder;
use DevSite\LaravelMigrationSearcher\Services\IndexGenerator;
use DevSite\LaravelMigrationSearcher\Services\IndexGeneratorFactory;
use DevSite\LaravelMigrationSearcher\Services\HtmlSanitizer;
use DevSite\LaravelMigrationSearcher\Services\MigrationAnalyzer;
use DevSite\LaravelMigrationSearcher\Support\MigrationFileInfo;
use DevSite\LaravelMigrationSearcher\Services\PathValidator;
use DevSite\LaravelMigrationSearcher\Services\RendererResolver;
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

    public function testBindsPathValidator(): void
    {
        $instance = $this->app->make(PathValidatorContract::class);
        $this->assertInstanceOf(PathValidator::class, $instance);
    }

    public function testBindsRendererResolver(): void
    {
        $instance = $this->app->make(RendererResolverContract::class);
        $this->assertInstanceOf(RendererResolver::class, $instance);
    }

    public function testFormatsConfigKeyExists(): void
    {
        $this->assertIsArray(config('migration-searcher.formats'));
    }

    public function testBindsTableDetectorContract(): void
    {
        $instance = $this->app->make(TableDetectorContract::class);
        $this->assertInstanceOf(TableDetector::class, $instance);
    }

    public function testBindsDdlParserContract(): void
    {
        $instance = $this->app->make(DdlParserContract::class);
        $this->assertInstanceOf(DdlParser::class, $instance);
    }

    public function testBindsDmlParserContract(): void
    {
        $instance = $this->app->make(DmlParserContract::class);
        $this->assertInstanceOf(DmlParser::class, $instance);
    }

    public function testBindsRawSqlParserContract(): void
    {
        $instance = $this->app->make(RawSqlParserContract::class);
        $this->assertInstanceOf(RawSqlParser::class, $instance);
    }

    public function testBindsDependencyParserContract(): void
    {
        $instance = $this->app->make(DependencyParserContract::class);
        $this->assertInstanceOf(DependencyParser::class, $instance);
    }

    public function testBindsComplexityCalculatorContract(): void
    {
        $instance = $this->app->make(ComplexityCalculatorContract::class);
        $this->assertInstanceOf(ComplexityCalculator::class, $instance);
    }

    public function testBindsMarkdownMigrationFormatterContract(): void
    {
        $instance = $this->app->make(MarkdownMigrationFormatterContract::class);
        $this->assertInstanceOf(MarkdownMigrationFormatter::class, $instance);
    }

    public function testBindsTextSanitizer(): void
    {
        $instance = $this->app->make(TextSanitizer::class);
        $this->assertInstanceOf(HtmlSanitizer::class, $instance);
    }

    public function testBindsIndexGeneratorFactory(): void
    {
        $instance = $this->app->make(IndexGeneratorFactoryContract::class);
        $this->assertInstanceOf(IndexGeneratorFactory::class, $instance);
    }

    public function testBindsMigrationFileInfoContract(): void
    {
        $instance = $this->app->make(MigrationFileInfoContract::class);
        $this->assertInstanceOf(MigrationFileInfo::class, $instance);
    }

    public function testMigrationAnalyzerReceivesMaxFileSizeFromConfig(): void
    {
        $this->app['config']->set('migration-searcher.max_file_size', 1024);
        $analyzer = $this->app->make(MigrationAnalyzerContract::class);

        $reflection = new \ReflectionProperty($analyzer, 'maxFileSize');
        $this->assertSame(1024, $reflection->getValue($analyzer));
    }

    public function testMigrationAnalyzerFallsBackToDefaultForInvalidMaxFileSize(): void
    {
        $this->app['config']->set('migration-searcher.max_file_size', -1);
        $analyzer = $this->app->make(MigrationAnalyzerContract::class);

        $reflection = new \ReflectionProperty($analyzer, 'maxFileSize');
        $this->assertSame(5242880, $reflection->getValue($analyzer));
    }

    public function testRendererResolverReceivesFormatsFromConfig(): void
    {
        $this->app['config']->set('migration-searcher.formats', [
            'custom' => MarkdownRenderer::class,
        ]);
        $resolver = $this->app->make(RendererResolverContract::class);

        $formats = $resolver->availableFormats();
        $this->assertContains('markdown', $formats);
        $this->assertContains('json', $formats);
        $this->assertContains('custom', $formats);
    }
}
