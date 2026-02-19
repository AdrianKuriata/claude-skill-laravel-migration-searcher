<?php

namespace Tests\Unit\Parsers;

use DevSite\LaravelMigrationSearcher\Services\Parsers\FileNameParser;
use Tests\TestCase;

class FileNameParserTest extends TestCase
{
    protected FileNameParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FileNameParser();
    }

    public function testExtractsTimestampFromStandardFilename(): void
    {
        $this->assertSame(
            '2024_01_15_100000',
            $this->parser->extractTimestamp('2024_01_15_100000_create_users_table.php')
        );
    }

    public function testReturnsUnknownForNonStandardFilename(): void
    {
        $this->assertSame('unknown', $this->parser->extractTimestamp('custom_migration.php'));
    }

    public function testExtractsMigrationName(): void
    {
        $this->assertSame(
            'create_users_table',
            $this->parser->extractMigrationName('2024_01_15_100000_create_users_table.php')
        );
    }

    public function testExtractsMigrationNameWithoutTimestamp(): void
    {
        $this->assertSame(
            'custom_migration',
            $this->parser->extractMigrationName('custom_migration.php')
        );
    }

    public function testGetRelativePathRemovesBasePath(): void
    {
        $basePath = base_path();
        $filepath = $basePath . '/database/migrations/test.php';

        $this->assertSame('database/migrations/test.php', $this->parser->getRelativePath($filepath));
    }

    public function testGetRelativePathReturnsOriginalWhenNotUnderBasePath(): void
    {
        $filepath = '/some/other/path/test.php';

        $this->assertSame($filepath, $this->parser->getRelativePath($filepath));
    }
}
