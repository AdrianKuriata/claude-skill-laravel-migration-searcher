<?php

namespace YourVendor\LaravelMigrationSearcher;

use Illuminate\Support\ServiceProvider;
use YourVendor\LaravelMigrationSearcher\Commands\IndexMigrationsCommand;

class MigrationSearcherServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/migration-searcher.php',
            'migration-searcher'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/migration-searcher.php' => config_path('migration-searcher.php'),
        ], 'migration-searcher-config');

        // Publish skill template
        $this->publishes([
            __DIR__.'/../resources/skill-template' => base_path('.claude/skills/laravel-migration-searcher'),
        ], 'migration-searcher-skill');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                IndexMigrationsCommand::class,
            ]);
        }
    }
}
