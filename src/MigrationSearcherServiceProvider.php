<?php

namespace DevSite\LaravelMigrationSearcher;

use DevSite\LaravelMigrationSearcher\Commands\IndexMigrationsCommand;
use DevSite\LaravelMigrationSearcher\Contracts\FileWriterInterface;
use DevSite\LaravelMigrationSearcher\Contracts\IndexGeneratorInterface;
use DevSite\LaravelMigrationSearcher\Contracts\MigrationAnalyzerInterface;
use DevSite\LaravelMigrationSearcher\Services\IndexGenerator;
use DevSite\LaravelMigrationSearcher\Services\MigrationAnalyzer;
use DevSite\LaravelMigrationSearcher\Services\Writers\IndexFileWriter;
use Illuminate\Support\ServiceProvider;

class MigrationSearcherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/migration-searcher.php',
            'migration-searcher'
        );

        $this->app->bind(FileWriterInterface::class, IndexFileWriter::class);
        $this->app->bind(MigrationAnalyzerInterface::class, MigrationAnalyzer::class);
        $this->app->bind(IndexGeneratorInterface::class, function ($app) {
            $outputPath = base_path(config('migration-searcher.output_path', '.claude/skills/laravel-migration-searcher'));
            return new IndexGenerator($outputPath);
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
