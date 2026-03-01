<?php

namespace DevSite\LaravelMigrationSearcher;

use DevSite\LaravelMigrationSearcher\Console\Commands\IndexMigrationsCommand;
use DevSite\LaravelMigrationSearcher\Contracts\Services\ComplexityCalculator as ComplexityCalculatorContract;
use DevSite\LaravelMigrationSearcher\Contracts\Parsers\DdlParser as DdlParserContract;
use DevSite\LaravelMigrationSearcher\Contracts\Parsers\DependencyParser as DependencyParserContract;
use DevSite\LaravelMigrationSearcher\Contracts\Parsers\DmlParser as DmlParserContract;
use DevSite\LaravelMigrationSearcher\Contracts\Writers\FileWriter;
use DevSite\LaravelMigrationSearcher\Contracts\Services\IndexDataBuilder as IndexDataBuilderContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\IndexGenerator as IndexGeneratorContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\IndexGeneratorFactory as IndexGeneratorFactoryContract;
use DevSite\LaravelMigrationSearcher\Contracts\Renderers\MarkdownMigrationFormatter as MarkdownMigrationFormatterContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\MigrationAnalyzer as MigrationAnalyzerContract;
use DevSite\LaravelMigrationSearcher\Contracts\Support\MigrationFileInfo as MigrationFileInfoContract;
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
use DevSite\LaravelMigrationSearcher\Services\PathValidator;
use DevSite\LaravelMigrationSearcher\Services\RendererResolver;
use DevSite\LaravelMigrationSearcher\Support\MigrationFileInfo;
use DevSite\LaravelMigrationSearcher\Writers\IndexFileWriter;
use Illuminate\Support\ServiceProvider;

class MigrationSearcherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/migration-searcher.php',
            'migration-searcher'
        );

        $this->app->bind(FileWriter::class, IndexFileWriter::class);
        $this->app->bind(TableDetectorContract::class, TableDetector::class);
        $this->app->bind(DdlParserContract::class, DdlParser::class);
        $this->app->bind(DmlParserContract::class, DmlParser::class);
        $this->app->bind(RawSqlParserContract::class, RawSqlParser::class);
        $this->app->bind(DependencyParserContract::class, DependencyParser::class);
        $this->app->bind(ComplexityCalculatorContract::class, ComplexityCalculator::class);
        $this->app->bind(MarkdownMigrationFormatterContract::class, MarkdownMigrationFormatter::class);
        $this->app->bind(TextSanitizer::class, HtmlSanitizer::class);
        $this->app->bind(MigrationFileInfoContract::class, MigrationFileInfo::class);
        $this->app->bind(MigrationAnalyzerContract::class, function ($app) {
            $maxFileSize = config('migration-searcher.max_file_size', 5242880);

            if (!is_int($maxFileSize) || $maxFileSize <= 0) {
                $maxFileSize = 5242880;
            }

            return new MigrationAnalyzer(
                $app->make(MigrationFileInfoContract::class),
                $app->make(TableDetectorContract::class),
                $app->make(DdlParserContract::class),
                $app->make(DmlParserContract::class),
                $app->make(RawSqlParserContract::class),
                $app->make(DependencyParserContract::class),
                $app->make(ComplexityCalculatorContract::class),
                $maxFileSize,
            );
        });
        $this->app->bind(IndexDataBuilderContract::class, IndexDataBuilder::class);
        $this->app->bind(IndexGeneratorFactoryContract::class, IndexGeneratorFactory::class);
        $this->app->bind(PathValidatorContract::class, fn () => new PathValidator(base_path()));
        $this->app->bind(RendererResolverContract::class, function ($app) {
            $defaults = [
                'markdown' => MarkdownRenderer::class,
                'json' => JsonRenderer::class,
            ];

            return new RendererResolver(array_merge($defaults, config('migration-searcher.formats', [])), $app);
        });

        $this->app->bind(Renderer::class, function () {
            $resolver = $this->app->make(RendererResolverContract::class);
            $format = config('migration-searcher.default_format', 'markdown');

            return $resolver->resolve($format);
        });

        $this->app->bind(IndexGeneratorContract::class, function ($app) {
            $outputPath = base_path(config('migration-searcher.output_path', '.claude/skills/laravel-migration-searcher'));

            return new IndexGenerator(
                $outputPath,
                $app->make(Renderer::class),
                $app->make(IndexDataBuilderContract::class),
                $app->make(FileWriter::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/migration-searcher.php' => config_path('migration-searcher.php'),
        ], 'migration-searcher-config');

        $this->publishes([
            __DIR__.'/../resources/skill-template' => base_path('.claude/skills/laravel-migration-searcher'),
        ], 'migration-searcher-skill');

        if ($this->app->runningInConsole()) {
            $this->commands([
                IndexMigrationsCommand::class,
            ]);
        }
    }
}
