<?php

namespace DevSite\LaravelMigrationSearcher\Commands;

use DevSite\LaravelMigrationSearcher\Contracts\IndexGeneratorInterface;
use DevSite\LaravelMigrationSearcher\Contracts\MigrationAnalyzerInterface;
use DevSite\LaravelMigrationSearcher\Services\IndexGenerator;
use DevSite\LaravelMigrationSearcher\Traits\FormatsFileSize;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class IndexMigrationsCommand extends Command
{
    use FormatsFileSize;

    protected $signature = 'migrations:index
                            {--type= : Type of migrations to index (as defined in config)}
                            {--refresh : Refresh existing index}
                            {--output= : Custom output path (overrides config)}';

    protected $description = 'Index all Laravel migrations and generate comprehensive documentation';

    protected array $migrationTypes = [];

    public function __construct(
        protected MigrationAnalyzerInterface $analyzer,
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('🔍 Starting Laravel migration indexing...');
        $this->newLine();

        $startTime = microtime(true);

        $this->migrationTypes = config('migration-searcher.migration_types', [
            'default' => [
                'path' => 'database/migrations',
            ],
        ]);

        $outputPath = $this->option('output')
            ?: base_path(config('migration-searcher.output_path', '.claude/skills/laravel-migration-searcher'));

        if ($this->option('refresh') && File::exists($outputPath)) {
            $this->warn('Cleaning existing index...');
            File::deleteDirectory($outputPath);
        }

        if (!File::exists($outputPath)) {
            File::makeDirectory($outputPath, 0755, true);
        }

        $typesToIndex = $this->determineTypesToIndex();

        if ($typesToIndex === null) {
            return Command::FAILURE;
        }

        $allMigrations = [];
        $stats = [];

        foreach ($typesToIndex as $type) {
            $this->info("📂 Indexing migrations: {$type}");

            $migrations = $this->indexMigrationType($type);
            $allMigrations = array_merge($allMigrations, $migrations);

            $stats[$type] = count($migrations);

            $this->line("   Found: " . count($migrations) . " migrations");
        }

        $this->newLine();
        $this->info("📊 Total found: " . count($allMigrations) . " migrations");
        $this->newLine();

        $this->info('📝 Generating index files...');

        $generator = new IndexGenerator($outputPath);
        $generator->setMigrations($allMigrations);
        $generated = $generator->generateAll();

        $this->newLine();
        $this->info('✅ Generated files:');
        foreach ($generated as $type => $filepath) {
            $size = File::exists($filepath) ? $this->formatFileSize(File::size($filepath)) : '0 B';
            $this->line("   - {$type}: {$filepath} ({$size})");
        }

        $skillPath = $outputPath . '/SKILL.md';
        if (!File::exists($skillPath)) {
            $this->info('📋 Copying SKILL.md template...');
            $templatePath = config('migration-searcher.skill_template_path');

            if (File::exists($templatePath)) {
                File::copy($templatePath, $skillPath);
            } else {
                $this->warn('   SKILL.md template not found - you may need to publish package resources');
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->newLine();
        $this->info("⏱️  Execution time: {$duration}s");
        $this->newLine();

        $this->displaySummary($stats, $outputPath);

        return Command::SUCCESS;
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
                $migrations[] = $migrationData;
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

    protected function displaySummary(array $stats, string $outputPath): void
    {
        $this->info('📈 Summary:');
        $this->newLine();

        $this->table(
            ['Type', 'Migrations Count'],
            collect($stats)->map(fn($count, $type) => [$type, $count])->values()->all()
        );

        $this->newLine();
        $this->info('💡 How to use:');
        $this->line('   1. Index is available at: ' . $outputPath);
        $this->line('   2. Commit .claude/ to git - whole team will have access');
        $this->line('   3. To refresh index: php artisan migrations:index --refresh');
        $this->line('   4. To index specific type: php artisan migrations:index --type=default');
        $this->newLine();

        $this->info('🔗 Next steps:');
        $this->line('   1. git add .claude/');
        $this->line('   2. git commit -m "Add migrations index"');
        $this->line('   3. Team does git pull and has access to index');
        $this->line('   4. Each developer can upload files to their Claude');
    }
}
