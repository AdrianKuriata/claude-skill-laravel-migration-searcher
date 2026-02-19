<?php

namespace Tests\Feature;

use DevSite\LaravelMigrationSearcher\Commands\IndexMigrationsCommand;
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
    }

    public function testCommandIsRegistered(): void
    {
        $commands = $this->app['Illuminate\Contracts\Console\Kernel']->all();

        $this->assertArrayHasKey('migrations:index', $commands);
        $this->assertInstanceOf(IndexMigrationsCommand::class, $commands['migrations:index']);
    }

}
