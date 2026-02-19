<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class IndexMigrationsCommandTest extends TestCase
{
    protected string $outputPath;
    protected string $migrationPath;

    protected function defineEnvironment($app): void
    {
        $this->outputPath = sys_get_temp_dir() . '/cmd-test-output-' . uniqid();

        $relativePath = 'test-migrations-' . uniqid();
        $this->migrationPath = $app->basePath($relativePath);

        mkdir($this->migrationPath, 0755, true);

        $fixturesPath = __DIR__ . '/../fixtures/migrations';
        foreach (glob($fixturesPath . '/*.php') as $file) {
            copy($file, $this->migrationPath . '/' . basename($file));
        }
        if (file_exists($fixturesPath . '/not_a_migration.txt')) {
            copy($fixturesPath . '/not_a_migration.txt', $this->migrationPath . '/not_a_migration.txt');
        }

        $app['config']->set('migration-searcher.output_path', $this->outputPath);
        $app['config']->set('migration-searcher.migration_types', [
            'default' => [
                'path' => $relativePath,
            ],
        ]);
        $app['config']->set('migration-searcher.skill_template_path', __DIR__ . '/../../resources/skill-template/SKILL.md');
    }

    protected function tearDown(): void
    {
        if (isset($this->outputPath) && is_dir($this->outputPath)) {
            File::deleteDirectory($this->outputPath);
        }
        if (isset($this->migrationPath) && is_dir($this->migrationPath)) {
            File::deleteDirectory($this->migrationPath);
        }
        parent::tearDown();
    }

    // ── Basic ───────────────────────────────────────────────────────

    public function testCommandOutputsFilePaths(): void
    {
        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->expectsOutputToContain('index-full')
            ->assertSuccessful();
    }

    // ── Options ─────────────────────────────────────────────────────

    public function testOutputOptionOverridesConfig(): void
    {
        $customOutput = sys_get_temp_dir() . '/custom-output-' . uniqid();

        $this->artisan('migrations:index', ['--output' => $customOutput])
            ->assertSuccessful();

        $this->assertDirectoryExists($customOutput);
        $this->assertFileExists($customOutput . '/index-full.md');

        File::deleteDirectory($customOutput);
    }

    public function testTypeOptionFiltersType(): void
    {
        $this->artisan('migrations:index', [
            '--output' => $this->outputPath,
            '--type' => 'default',
        ])->assertSuccessful();

        $this->assertFileExists($this->outputPath . '/stats.json');
    }

    public function testInvalidTypeShowsError(): void
    {
        $this->artisan('migrations:index', [
            '--output' => $this->outputPath,
            '--type' => 'nonexistent_type',
        ])
            ->expectsOutputToContain('Invalid type')
            ->assertFailed();
    }

    public function testRefreshDeletesAndRecreates(): void
    {
        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->assertSuccessful();

        $markerFile = $this->outputPath . '/should-be-deleted.txt';
        file_put_contents($markerFile, 'marker');

        $this->artisan('migrations:index', [
            '--output' => $this->outputPath,
            '--refresh' => true,
        ])->assertSuccessful();

        $this->assertFileDoesNotExist($markerFile);
        $this->assertFileExists($this->outputPath . '/index-full.md');
    }

    public function testRefreshOnNonExistentDirectory(): void
    {
        $this->artisan('migrations:index', [
            '--output' => $this->outputPath,
            '--refresh' => true,
        ])->assertSuccessful();

        $this->assertDirectoryExists($this->outputPath);
    }

    // ── SKILL.md ────────────────────────────────────────────────────

    public function testCopiesSkillMdTemplate(): void
    {
        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->assertSuccessful();

        $this->assertFileExists($this->outputPath . '/SKILL.md');
    }

    public function testSkillMdNotOverwrittenIfExists(): void
    {
        mkdir($this->outputPath, 0755, true);
        $skillPath = $this->outputPath . '/SKILL.md';
        file_put_contents($skillPath, 'CUSTOM CONTENT');

        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->assertSuccessful();

        $this->assertSame('CUSTOM CONTENT', file_get_contents($skillPath));
    }

    public function testWarnsWhenTemplateNotFound(): void
    {
        $this->app['config']->set('migration-searcher.skill_template_path', '/nonexistent/SKILL.md');

        @unlink($this->outputPath . '/SKILL.md');

        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->expectsOutputToContain('template not found')
            ->assertSuccessful();
    }

    // ── Filtering/Edge Cases ────────────────────────────────────────

    public function testSkipsNonPhpFiles(): void
    {
        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->assertSuccessful();

        $stats = json_decode(file_get_contents($this->outputPath . '/stats.json'), true);

        $phpFiles = count(glob($this->migrationPath . '/*.php'));
        $this->assertSame($phpFiles, $stats['total_migrations']);
    }

    public function testHandlesEmptyDirectory(): void
    {
        $emptyDir = sys_get_temp_dir() . '/empty-migrations-' . uniqid();
        mkdir($emptyDir, 0755, true);

        $this->app['config']->set('migration-searcher.migration_types', [
            'default' => ['path' => $emptyDir],
        ]);

        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->assertSuccessful();

        $stats = json_decode(file_get_contents($this->outputPath . '/stats.json'), true);
        $this->assertSame(0, $stats['total_migrations']);

        File::deleteDirectory($emptyDir);
    }

    public function testHandlesNonExistentMigrationPath(): void
    {
        $this->app['config']->set('migration-searcher.migration_types', [
            'default' => ['path' => '/nonexistent/path/migrations'],
        ]);

        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->assertSuccessful();
    }

    // ── Security ────────────────────────────────────────────────────

    public function testOutputDirectoryPermissions(): void
    {
        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->assertSuccessful();

        $perms = fileperms($this->outputPath) & 0777;
        $this->assertSame(0755, $perms);
    }

    /**
     * @group security-bug
     */
    public function testPathTraversalBlocked(): void
    {
        $this->markTestSkipped(
            'SECURITY BUG: No path traversal validation on --output option. '
            . 'Needs validation before File::makeDirectory() call.'
        );
    }

    // ── Config default path ───────────────────────────────────────

    public function testUsesConfigOutputPathWhenNoOutputOption(): void
    {
        $configPath = 'test-index-output-' . uniqid();
        $this->app['config']->set('migration-searcher.output_path', $configPath);

        $expectedPath = base_path($configPath);

        $this->artisan('migrations:index')
            ->assertSuccessful();

        $this->assertDirectoryExists($expectedPath);
        $this->assertFileExists($expectedPath . '/index-full.md');

        File::deleteDirectory($expectedPath);
    }

    // ── Exception handling ──────────────────────────────────────────

    public function testExceptionDuringAnalysisShowsError(): void
    {
        $brokenFile = $this->migrationPath . '/2099_01_01_000000_broken.php';
        symlink('/nonexistent/path/file.php', $brokenFile);

        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->expectsOutputToContain('Error analyzing')
            ->assertSuccessful();

        @unlink($brokenFile);
    }
}
