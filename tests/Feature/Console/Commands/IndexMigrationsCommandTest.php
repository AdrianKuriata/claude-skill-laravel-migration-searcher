<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class IndexMigrationsCommandTest extends TestCase
{
    protected string $outputPath;
    protected string $migrationPath;

    protected function defineEnvironment($app): void
    {
        $this->outputPath = $app->basePath('test-output-' . uniqid());

        $relativePath = 'test-migrations-' . uniqid();
        $this->migrationPath = $app->basePath($relativePath);

        mkdir($this->migrationPath, 0755, true);

        $fixturesPath = __DIR__ . '/../../../fixtures/migrations';
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
        $app['config']->set('migration-searcher.skill_template_path', __DIR__ . '/../../../../resources/skill-template/SKILL.md');
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
        $customOutput = base_path('custom-output-' . uniqid());

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

        $this->assertFileExists($this->outputPath . '/stats.md');
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

    public function testRefreshRecreatesIndexFiles(): void
    {
        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->assertSuccessful();

        $this->assertFileExists($this->outputPath . '/index-full.md');

        $this->artisan('migrations:index', [
            '--output' => $this->outputPath,
            '--refresh' => true,
        ])->assertSuccessful();

        $this->assertFileExists($this->outputPath . '/index-full.md');
        $this->assertFileExists($this->outputPath . '/stats.md');
    }

    public function testRefreshPreservesSkillMd(): void
    {
        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->assertSuccessful();

        $skillPath = $this->outputPath . '/SKILL.md';
        file_put_contents($skillPath, 'CUSTOM USER CONTENT');

        $this->artisan('migrations:index', [
            '--output' => $this->outputPath,
            '--refresh' => true,
        ])->assertSuccessful();

        $this->assertFileExists($skillPath);
        $this->assertSame('CUSTOM USER CONTENT', file_get_contents($skillPath));
    }

    public function testRefreshCleansAllGeneratedFormats(): void
    {
        $this->artisan('migrations:index', [
            '--output' => $this->outputPath,
            '--format' => 'json',
        ])->assertSuccessful();

        $this->assertFileExists($this->outputPath . '/index-full.json');

        $this->artisan('migrations:index', [
            '--output' => $this->outputPath,
            '--refresh' => true,
            '--format' => 'markdown',
        ])->assertSuccessful();

        $this->assertFileDoesNotExist($this->outputPath . '/index-full.json');
        $this->assertFileExists($this->outputPath . '/index-full.md');
    }

    public function testRefreshDoesNotDeleteNonGeneratedFiles(): void
    {
        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->assertSuccessful();

        $userFile = $this->outputPath . '/my-notes.txt';
        file_put_contents($userFile, 'user content');

        $this->artisan('migrations:index', [
            '--output' => $this->outputPath,
            '--refresh' => true,
        ])->assertSuccessful();

        $this->assertFileExists($userFile);
        $this->assertSame('user content', file_get_contents($userFile));
    }

    public function testRefreshOnNonExistentDirectory(): void
    {
        $this->artisan('migrations:index', [
            '--output' => $this->outputPath,
            '--refresh' => true,
        ])->assertSuccessful();

        $this->assertDirectoryExists($this->outputPath);
    }

    // ── Format option ──────────────────────────────────────────────

    public function testFormatOptionDefaultsToMarkdown(): void
    {
        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->assertSuccessful();

        $this->assertFileExists($this->outputPath . '/index-full.md');
        $this->assertFileExists($this->outputPath . '/index-by-type.md');
    }

    public function testFormatOptionJson(): void
    {
        $this->artisan('migrations:index', [
            '--output' => $this->outputPath,
            '--format' => 'json',
        ])->assertSuccessful();

        $this->assertFileExists($this->outputPath . '/index-full.json');
        $this->assertFileExists($this->outputPath . '/index-by-type.json');
        $this->assertFileExists($this->outputPath . '/index-by-table.json');
        $this->assertFileExists($this->outputPath . '/index-by-operation.json');
        $this->assertFileExists($this->outputPath . '/stats.json');

        $fullIndex = json_decode(file_get_contents($this->outputPath . '/index-full.json'), true);
        $this->assertNotNull($fullIndex);
        $this->assertArrayHasKey('migrations', $fullIndex);
    }

    public function testFormatOptionJsonStatsContainsCorrectData(): void
    {
        $this->artisan('migrations:index', [
            '--output' => $this->outputPath,
            '--format' => 'json',
        ])->assertSuccessful();

        $stats = json_decode(file_get_contents($this->outputPath . '/stats.json'), true);
        $this->assertNotNull($stats);
        $this->assertArrayHasKey('total_migrations', $stats);
        $this->assertGreaterThan(0, $stats['total_migrations']);
    }

    public function testInvalidFormatShowsError(): void
    {
        $this->artisan('migrations:index', [
            '--output' => $this->outputPath,
            '--format' => 'xml',
        ])
            ->expectsOutputToContain('Unsupported format')
            ->assertFailed();
    }

    public function testCustomRendererViaFormatsConfig(): void
    {
        $this->app['config']->set('migration-searcher.formats', [
            'custom' => \DevSite\LaravelMigrationSearcher\Renderers\JsonRenderer::class,
        ]);

        $this->artisan('migrations:index', [
            '--output' => $this->outputPath,
            '--format' => 'custom',
        ])->assertSuccessful();

        $this->assertFileExists($this->outputPath . '/index-full.json');
    }

    public function testFormatOptionRespectsConfigDefault(): void
    {
        $this->app['config']->set('migration-searcher.default_format', 'json');

        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->assertSuccessful();

        $this->assertFileExists($this->outputPath . '/index-full.json');
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

        $content = file_get_contents($this->outputPath . '/stats.md');

        $phpFiles = count(glob($this->migrationPath . '/*.php'));
        $this->assertStringContainsString('**Total migrations:** ' . $phpFiles, $content);
    }

    public function testHandlesEmptyDirectory(): void
    {
        $emptyRelative = 'empty-migrations-' . uniqid();
        $emptyDir = base_path($emptyRelative);
        mkdir($emptyDir, 0755, true);

        $this->app['config']->set('migration-searcher.migration_types', [
            'default' => ['path' => $emptyRelative],
        ]);

        $this->artisan('migrations:index', ['--output' => $this->outputPath])
            ->assertSuccessful();

        $content = file_get_contents($this->outputPath . '/stats.md');
        $this->assertStringContainsString('**Total migrations:** 0', $content);

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

    public function testPathTraversalBlocked(): void
    {
        $this->artisan('migrations:index', [
            '--output' => '/tmp/../tmp/traversal-test',
        ])
            ->expectsOutputToContain('Output path must be within the project root directory')
            ->assertFailed();
    }

    public function testPathTraversalBlockedWithNonExistentParent(): void
    {
        $this->artisan('migrations:index', [
            '--output' => '/nonexistent-abc/nonexistent-def/output',
        ])
            ->expectsOutputToContain('Output path must be within the project root directory')
            ->assertFailed();
    }

    public function testPathTraversalBlockedWithDeepRelativeEscape(): void
    {
        $this->artisan('migrations:index', [
            '--output' => base_path('a/b/../../../../tmp/evil-output'),
        ])
            ->expectsOutputToContain('Output path must be within the project root directory')
            ->assertFailed();
    }

    public function testPathTraversalBlockedWithDeeplyNestedNonExistentAbsolutePath(): void
    {
        $this->artisan('migrations:index', [
            '--output' => '/tmp/nonexistent-' . uniqid() . '/deep/nested/output',
        ])
            ->expectsOutputToContain('Output path must be within the project root directory')
            ->assertFailed();
    }

    public function testPathWithNonExistentParentButValidGrandparent(): void
    {
        $outputPath = base_path('nonexistent-' . uniqid() . '/output');

        $this->artisan('migrations:index', ['--output' => $outputPath])
            ->assertSuccessful();

        File::deleteDirectory(dirname($outputPath));
    }

    public function testPathWithDeeplyNestedNonExistentDirectories(): void
    {
        $outputPath = base_path('nonexistent-' . uniqid() . '/nonexistent/nested/output');

        $this->artisan('migrations:index', ['--output' => $outputPath])
            ->assertSuccessful();

        File::deleteDirectory(base_path(explode('/', str_replace(base_path('/'), '', $outputPath))[0]));
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
