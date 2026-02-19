<?php

namespace Tests\Feature;

use DevSite\LaravelMigrationSearcher\Commands\IndexMigrationsCommand;
use DevSite\LaravelMigrationSearcher\Contracts\FileWriterInterface;
use DevSite\LaravelMigrationSearcher\Contracts\IndexGeneratorInterface;
use DevSite\LaravelMigrationSearcher\Contracts\MigrationAnalyzerInterface;
use DevSite\LaravelMigrationSearcher\Services\IndexGenerator;
use DevSite\LaravelMigrationSearcher\Services\MigrationAnalyzer;
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
}
