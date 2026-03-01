<?php

namespace Tests\Unit\Support;

use DevSite\LaravelMigrationSearcher\Exceptions\InvalidFileExtensionException;
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

    public function testGetFileSizeReturnsFileSize(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'mfi_test_') . '.php';
        file_put_contents($tmpFile, 'hello world');

        try {
            $this->assertSame(11, $this->migrationFileInfo->getFileSize($tmpFile));
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testGetContentsReturnsFileContents(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'mfi_test_') . '.php';
        file_put_contents($tmpFile, '<?php echo 1;');

        try {
            $this->assertSame('<?php echo 1;', $this->migrationFileInfo->getContents($tmpFile));
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testGetContentsThrowsForNonPhpFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'mfi_test_') . '.txt';
        file_put_contents($tmpFile, 'content');

        try {
            $this->expectException(InvalidFileExtensionException::class);
            $this->migrationFileInfo->getContents($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testGetFileSizeThrowsForNonPhpFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'mfi_test_') . '.txt';
        file_put_contents($tmpFile, 'content');

        try {
            $this->expectException(InvalidFileExtensionException::class);
            $this->migrationFileInfo->getFileSize($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }
}
