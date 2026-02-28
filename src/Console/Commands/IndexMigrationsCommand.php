<?php

namespace DevSite\LaravelMigrationSearcher\Console\Commands;

use DevSite\LaravelMigrationSearcher\Contracts\IndexDataBuilder as IndexDataBuilderContract;
use DevSite\LaravelMigrationSearcher\Contracts\MigrationAnalyzer as MigrationAnalyzerContract;
use DevSite\LaravelMigrationSearcher\Contracts\Renderer;
use DevSite\LaravelMigrationSearcher\Services\IndexGenerator;
use DevSite\LaravelMigrationSearcher\Renderers\JsonRenderer;
use DevSite\LaravelMigrationSearcher\Renderers\MarkdownRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class IndexMigrationsCommand extends Command
{
    protected $signature = 'migrations:index
                            {--type= : Type of migrations to index (as defined in config)}
                            {--format= : Output format (markdown, json)}
                            {--refresh : Refresh existing index}
                            {--output= : Custom output path (overrides config)}';

    protected $description = 'Index all Laravel migrations and generate comprehensive documentation';

    protected array $migrationTypes = [];

    public function __construct(
        protected MigrationAnalyzerContract $analyzer,
        protected IndexDataBuilderContract $dataBuilder,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->migrationTypes = config('migration-searcher.migration_types', [
            'default' => ['path' => 'database/migrations'],
        ]);

        $outputPath = $this->resolveOutputPath();
        if ($outputPath === null) {
            return Command::FAILURE;
        }

        $renderer = $this->resolveFormat();
        if ($renderer === null) {
            return Command::FAILURE;
        }

        $this->prepareOutputDirectory($outputPath);

        $typesToIndex = $this->determineTypesToIndex();
        if ($typesToIndex === null) {
            return Command::FAILURE;
        }

        $result = $this->collectMigrations($typesToIndex);
        $generated = $this->generateIndexFiles($result['migrations'], $outputPath, $renderer);

        $this->displayGeneratedFiles($generated);
        $this->copySkillTemplate($outputPath);
        $this->displaySummary($result['stats']);

        return Command::SUCCESS;
    }

    protected function resolveOutputPath(): ?string
    {
        $outputPath = $this->option('output')
            ?: base_path(config('migration-searcher.output_path', '.claude/skills/laravel-migration-searcher'));

        if (!$this->isPathWithinBase($outputPath)) {
            $this->error('Output path must be within the project root directory.');
            return null;
        }

        return $outputPath;
    }

    protected function resolveFormat(): ?Renderer
    {
        $format = $this->option('format')
            ?: config('migration-searcher.default_format', 'markdown');

        $renderer = $this->resolveRenderer($format);

        if ($renderer === null) {
            $this->error("Unsupported format: {$format}. Available formats: markdown, json");
            return null;
        }

        return $renderer;
    }

    protected function prepareOutputDirectory(string $outputPath): void
    {
        if ($this->option('refresh') && File::exists($outputPath)) {
            $this->warn('Cleaning existing index...');
            $this->cleanGeneratedFiles($outputPath);
        }

        if (!File::exists($outputPath)) {
            File::makeDirectory($outputPath, 0755, true);
        }
    }

    protected function collectMigrations(array $types): array
    {
        $allMigrations = [];
        $stats = [];

        foreach ($types as $type) {
            $this->info("Indexing migrations: {$type}");

            $migrations = $this->indexMigrationType($type);
            $allMigrations = array_merge($allMigrations, $migrations);

            $stats[$type] = count($migrations);

            $this->line("   Found: " . count($migrations) . " migrations");
        }

        $this->newLine();
        $this->info("Total found: " . count($allMigrations) . " migrations");

        return ['migrations' => $allMigrations, 'stats' => $stats];
    }

    protected function generateIndexFiles(array $migrations, string $outputPath, Renderer $renderer): array
    {
        $this->info('Generating index files...');

        $generator = new IndexGenerator($outputPath, $renderer, $this->dataBuilder);
        $generator->setMigrations($migrations);

        return $generator->generateAll();
    }

    protected function displayGeneratedFiles(array $generated): void
    {
        $this->newLine();
        $this->info('Generated files:');

        foreach ($generated as $type => $filepath) {
            $this->line("   - {$type}: {$filepath}");
        }
    }

    protected function copySkillTemplate(string $outputPath): void
    {
        $skillPath = $outputPath . '/SKILL.md';

        if (File::exists($skillPath)) {
            return;
        }

        $this->info('Copying SKILL.md template...');
        $templatePath = config('migration-searcher.skill_template_path');

        if (File::exists($templatePath)) {
            File::copy($templatePath, $skillPath);
        } else {
            $this->warn('   SKILL.md template not found - you may need to publish package resources');
        }
    }

    protected function determineTypesToIndex(): ?array
    {
        if ($type = $this->option('type')) {
            if (!isset($this->migrationTypes[$type])) {
                $this->error("Invalid type: {$type}");
                $this->line("Available types: " . implode(', ', array_keys($this->migrationTypes)));
                return null;
            }
            return [$type];
        }

        return array_keys($this->migrationTypes);
    }

    protected function indexMigrationType(string $type): array
    {
        $typeConfig = $this->migrationTypes[$type];
        $path = base_path($typeConfig['path']);

        if (!File::exists($path)) {
            $this->warn("   Directory doesn't exist: {$path}");
            return [];
        }

        $files = File::files($path);
        $migrations = [];

        $progressBar = $this->output->createProgressBar(count($files));
        $progressBar->setFormat('   [%bar%] %current%/%max% (%percent:3s%%) %message%');
        $progressBar->setMessage('Analyzing...');
        $progressBar->start();

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $progressBar->setMessage('Analyzing: ' . $file->getFilename());

            try {
                $migrationData = $this->analyzer->analyze($file->getPathname(), $type);
                $migrations[] = $migrationData->toArray();
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("   Error analyzing {$file->getFilename()}: " . $e->getMessage());
                $this->newLine();
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        return $migrations;
    }

    protected function isPathWithinBase(string $path): bool
    {
        $basePath = realpath(base_path());
        $checkPath = dirname($this->normalizePath($path));

        while (true) {
            $resolvedPath = realpath($checkPath);
            if ($resolvedPath !== false) {
                return str_starts_with($resolvedPath, $basePath);
            }

            $checkPath = dirname($checkPath);
        }
    }

    protected function normalizePath(string $path): string
    {
        $isAbsolute = str_starts_with($path, '/');
        $parts = array_filter(explode('/', $path), fn ($part) => $part !== '' && $part !== '.');
        $normalized = [];

        foreach ($parts as $part) {
            if ($part === '..' && !empty($normalized) && end($normalized) !== '..') {
                array_pop($normalized);
            } else {
                $normalized[] = $part;
            }
        }

        return ($isAbsolute ? '/' : '') . implode('/', $normalized);
    }

    protected function resolveRenderer(string $format): ?Renderer
    {
        return match ($format) {
            'markdown' => new MarkdownRenderer(),
            'json' => new JsonRenderer(),
            default => null,
        };
    }

    protected function cleanGeneratedFiles(string $outputPath): void
    {
        $patterns = ['index-*', 'stats.json'];

        foreach ($patterns as $pattern) {
            foreach (File::glob($outputPath . '/' . $pattern) as $file) {
                File::delete($file);
            }
        }
    }

    protected function displaySummary(array $stats): void
    {
        $this->newLine();
        $this->info('Summary:');

        $this->table(
            ['Type', 'Migrations Count'],
            collect($stats)->map(fn ($count, $type) => [$type, $count])->values()->all()
        );
    }
}
