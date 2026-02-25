<?php

namespace DevSite\LaravelMigrationSearcher;

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
        $this->app->bind(MigrationAnalyzerContract::class, MigrationAnalyzer::class);
        $this->app->bind(IndexDataBuilderContract::class, IndexDataBuilder::class);

        $this->app->bind(Renderer::class, function () {
            return match (config('migration-searcher.default_format', 'markdown')) {
                'json' => new JsonRenderer(),
                default => new MarkdownRenderer(),
            };
        });

        $this->app->bind(IndexGeneratorContract::class, function ($app) {
            $outputPath = base_path(config('migration-searcher.output_path', '.claude/skills/laravel-migration-searcher'));

            return new IndexGenerator(
                $outputPath,
                $app->make(Renderer::class),
                $app->make(IndexDataBuilderContract::class),
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
