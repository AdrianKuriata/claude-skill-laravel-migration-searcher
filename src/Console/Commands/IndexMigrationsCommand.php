<?php

namespace DevSite\LaravelMigrationSearcher\Console\Commands;

use DevSite\LaravelMigrationSearcher\Contracts\FileWriter;
use DevSite\LaravelMigrationSearcher\Contracts\IndexDataBuilder as IndexDataBuilderContract;
use DevSite\LaravelMigrationSearcher\Contracts\MigrationAnalyzer as MigrationAnalyzerContract;
use DevSite\LaravelMigrationSearcher\Contracts\PathValidator as PathValidatorContract;
use DevSite\LaravelMigrationSearcher\Contracts\Renderer;
use DevSite\LaravelMigrationSearcher\Contracts\RendererResolver as RendererResolverContract;
use DevSite\LaravelMigrationSearcher\Services\IndexGenerator;
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
        protected PathValidatorContract $pathValidator,
        protected RendererResolverContract $rendererResolver,
        protected FileWriter $fileWriter,
    ) {
        $this->migrationTypes = config('migration-searcher.migration_types', [
            'default' => ['path' => 'database/migrations'],
        ]);
        parent::__construct();
    }

    public function handle(): int
    {
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

        ['migrations' => $migrations, 'stats' => $stats] = $this->collectMigrations($typesToIndex);
        $generated = $this->generateIndexFiles($migrations, $outputPath, $renderer);

        $this->displayGeneratedFiles($generated);
        $this->copySkillTemplate($outputPath);
        $this->displaySummary($stats);

        return Command::SUCCESS;
    }

    protected function resolveOutputPath(): ?string
    {
        $outputPath = $this->option('output')
            ?: base_path(config('migration-searcher.output_path', '.claude/skills/laravel-migration-searcher'));

        if (!$this->pathValidator->isWithinBasePath($outputPath)) {
            $this->error('Output path must be within the project root directory.');
            return null;
        }

        return $outputPath;
    }

    protected function resolveFormat(): ?Renderer
    {
        $format = $this->option('format')
            ?: config('migration-searcher.default_format', 'markdown');

        $renderer = $this->rendererResolver->resolve($format);

        if ($renderer === null) {
            $available = implode(', ', $this->rendererResolver->availableFormats());
            $this->error("Unsupported format: {$format}. Available formats: {$available}");
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

    /**
     * @return array{migrations: array, stats: array}
     */
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

        $generator = new IndexGenerator($outputPath, $renderer, $this->dataBuilder, $this->fileWriter);
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

        if ($templatePath && File::exists($templatePath)) {
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

        $allFiles = File::files($path);
        $files = array_values(array_filter($allFiles, fn($file) => $file->getExtension() === 'php'));
        $migrations = [];

        $progressBar = $this->output->createProgressBar(count($files));
        $progressBar->setFormat('   [%bar%] %current%/%max% (%percent:3s%%) %message%');
        $progressBar->setMessage('Analyzing...');
        $progressBar->start();

        foreach ($files as $file) {
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
            collect($stats)->map(fn($count, $type) => [$type, $count])->values()->all()
        );
    }
}
