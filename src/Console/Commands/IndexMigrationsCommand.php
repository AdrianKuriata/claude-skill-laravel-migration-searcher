<?php

namespace DevSite\LaravelMigrationSearcher\Console\Commands;

use DevSite\LaravelMigrationSearcher\Contracts\Services\IndexGeneratorFactory as IndexGeneratorFactoryContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\MigrationAnalyzer as MigrationAnalyzerContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\PathValidator as PathValidatorContract;
use DevSite\LaravelMigrationSearcher\Contracts\Renderers\Renderer;
use DevSite\LaravelMigrationSearcher\Contracts\Renderers\RendererResolver as RendererResolverContract;
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

    /** @var array<string, array{path: string}> */
    protected array $migrationTypes = [];

    public function __construct(
        protected MigrationAnalyzerContract $analyzer,
        protected IndexGeneratorFactoryContract $generatorFactory,
        protected PathValidatorContract $pathValidator,
        protected RendererResolverContract $rendererResolver,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $configTypes = config('migration-searcher.migration_types');
        /** @var array<string, array{path: string}> $defaultTypes */
        $defaultTypes = ['default' => ['path' => 'database/migrations']];
        /** @var array<string, array{path: string}> $resolvedTypes */
        $resolvedTypes = is_array($configTypes) ? $configTypes : $defaultTypes;
        $this->migrationTypes = $resolvedTypes;

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
        $customOutput = $this->option('output');
        $configPath = config('migration-searcher.output_path', '.claude/skills/laravel-migration-searcher');

        $outputPath = is_string($customOutput) && $customOutput !== ''
            ? $customOutput
            : base_path(is_string($configPath) ? $configPath : '.claude/skills/laravel-migration-searcher');

        if (!$this->pathValidator->isWithinBasePath($outputPath)) {
            $this->error('Output path must be within the project root directory.');
            return null;
        }

        return $outputPath;
    }

    protected function resolveFormat(): ?Renderer
    {
        $optionFormat = $this->option('format');
        $configFormat = config('migration-searcher.default_format', 'markdown');
        $format = is_string($optionFormat) && $optionFormat !== ''
            ? $optionFormat
            : (is_string($configFormat) ? $configFormat : 'markdown');

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
     * @param string[] $types
     * @return array{migrations: list<array<string, mixed>>, stats: array<string, int>}
     */
    protected function collectMigrations(array $types): array
    {
        /** @var list<array<string, mixed>> $allMigrations */
        $allMigrations = [];
        /** @var array<string, int> $stats */
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

    /**
     * @param list<array<string, mixed>> $migrations
     * @return array<string, string>
     */
    protected function generateIndexFiles(array $migrations, string $outputPath, Renderer $renderer): array
    {
        $this->info('Generating index files...');

        $generator = $this->generatorFactory->create($outputPath, $renderer);

        return $generator->generateAll($migrations);
    }

    /** @param array<string, string> $generated */
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
        $templatePath = dirname(__DIR__, 3) . '/resources/skill-template/SKILL.md';

        if (!File::exists($templatePath)) {
            $this->warn('   SKILL.md template not found - you may need to publish package resources');
            return;
        }

        File::copy($templatePath, $skillPath);
    }

    /** @return string[]|null */
    protected function determineTypesToIndex(): ?array
    {
        $type = $this->option('type');
        if (is_string($type) && $type !== '') {
            if (!isset($this->migrationTypes[$type])) {
                $this->error("Invalid type: {$type}");
                $this->line("Available types: " . implode(', ', array_keys($this->migrationTypes)));
                return null;
            }
            return [$type];
        }

        return array_keys($this->migrationTypes);
    }

    /** @return list<array<string, mixed>> */
    protected function indexMigrationType(string $type): array
    {
        $typeConfig = $this->migrationTypes[$type];
        $path = base_path($typeConfig['path']);

        if (!$this->pathValidator->isWithinBasePath($path)) {
            $this->error("Migration path is outside the project root: {$typeConfig['path']}");
            return [];
        }

        if (!File::exists($path)) {
            $this->warn("   Directory doesn't exist: {$path}");
            return [];
        }

        $allFiles = File::files($path);
        $files = array_values(array_filter($allFiles, fn ($file) => $file->getExtension() === 'php'));
        /** @var list<array<string, mixed>> $migrations */
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
        $patterns = ['index-*', 'stats.*'];
        $escapedPath = str_replace(
            ['\\', '*', '?', '['],
            ['\\\\', '\\*', '\\?', '\\['],
            $outputPath
        );

        foreach ($patterns as $pattern) {
            $files = File::glob($escapedPath . '/' . $pattern);
            foreach ($files as $file) {
                if (is_string($file)) {
                    File::delete($file);
                }
            }
        }
    }

    /** @param array<string, int> $stats */
    protected function displaySummary(array $stats): void
    {
        $this->newLine();
        $this->info('Summary:');

        $this->table(
            ['Type', 'Migrations Count'],
            collect($stats)->map(fn (int $count, string $type): array => [$type, $count])->values()->all()
        );
    }
}
