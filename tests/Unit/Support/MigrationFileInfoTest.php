<?php

namespace Tests\Unit\Support;

use DevSite\LaravelMigrationSearcher\Support\MigrationFileInfo;
use Tests\TestCase;

class MigrationFileInfoTest extends TestCase
{
    protected MigrationFileInfo $migrationFileInfo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrationFileInfo = new MigrationFileInfo();
    }

    public function testExtractsTimestampFromStandardFilename(): void
    {
        $this->assertSame(
            '2024_01_15_100000',
            $this->migrationFileInfo->extractTimestamp('2024_01_15_100000_create_users_table.php')
        );
    }

    public function testReturnsUnknownForNonStandardFilename(): void
    {
        $this->assertSame('unknown', $this->migrationFileInfo->extractTimestamp('custom_migration.php'));
    }

    public function testExtractsMigrationName(): void
    {
        $this->assertSame(
            'create_users_table',
            $this->migrationFileInfo->extractMigrationName('2024_01_15_100000_create_users_table.php')
        );
    }

    public function testExtractsMigrationNameWithoutTimestamp(): void
    {
        $this->assertSame(
            'custom_migration',
            $this->migrationFileInfo->extractMigrationName('custom_migration.php')
        );
    }

    public function testGetRelativePathRemovesBasePath(): void
    {
        $basePath = base_path();
        $filepath = $basePath . '/database/migrations/test.php';

        $this->assertSame('database/migrations/test.php', $this->migrationFileInfo->getRelativePath($filepath));
    }

    public function testGetRelativePathReturnsOriginalWhenNotUnderBasePath(): void
    {
        $filepath = '/some/other/path/test.php';

        $this->assertSame($filepath, $this->migrationFileInfo->getRelativePath($filepath));
    }
}
