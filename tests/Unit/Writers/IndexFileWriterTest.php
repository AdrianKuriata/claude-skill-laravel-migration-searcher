<?php

namespace Tests\Unit\Writers;

use DevSite\LaravelMigrationSearcher\Services\Writers\IndexFileWriter;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class IndexFileWriterTest extends TestCase
{
    protected IndexFileWriter $writer;
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->writer = new IndexFileWriter();
        $this->tempDir = sys_get_temp_dir() . '/writer-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function testWriteCreatesFile(): void
    {
        mkdir($this->tempDir, 0755, true);
        $filepath = $this->tempDir . '/test.md';

        $this->writer->write($filepath, 'Hello World');

        $this->assertFileExists($filepath);
        $this->assertSame('Hello World', file_get_contents($filepath));
    }

    public function testWriteOverwritesExistingFile(): void
    {
        mkdir($this->tempDir, 0755, true);
        $filepath = $this->tempDir . '/test.md';

        $this->writer->write($filepath, 'First');
        $this->writer->write($filepath, 'Second');

        $this->assertSame('Second', file_get_contents($filepath));
    }

    public function testEnsureDirectoryCreatesDirectory(): void
    {
        $this->assertDirectoryDoesNotExist($this->tempDir);

        $this->writer->ensureDirectory($this->tempDir);

        $this->assertDirectoryExists($this->tempDir);
    }

    public function testEnsureDirectoryDoesNothingIfExists(): void
    {
        mkdir($this->tempDir, 0755, true);

        $this->writer->ensureDirectory($this->tempDir);

        $this->assertDirectoryExists($this->tempDir);
    }

    public function testEnsureDirectoryCreatesNestedDirectories(): void
    {
        $nested = $this->tempDir . '/a/b/c';

        $this->writer->ensureDirectory($nested);

        $this->assertDirectoryExists($nested);
    }
}
