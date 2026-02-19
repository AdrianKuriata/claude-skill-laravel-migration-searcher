<?php

namespace Tests\Unit\Traits;

use DevSite\LaravelMigrationSearcher\Traits\FormatsFileSize;
use PHPUnit\Framework\TestCase;

class FormatsFileSizeTest extends TestCase
{
    use FormatsFileSize;

    public function testBytes(): void
    {
        $this->assertSame('512 B', $this->formatFileSize(512));
    }

    public function testKilobytes(): void
    {
        $this->assertSame('2 KB', $this->formatFileSize(2048));
    }

    public function testMegabytes(): void
    {
        $this->assertSame('2 MB', $this->formatFileSize(2 * 1024 * 1024));
    }

    public function testGigabytes(): void
    {
        $this->assertSame('2 GB', $this->formatFileSize(2 * 1024 * 1024 * 1024));
    }

    public function testZeroBytes(): void
    {
        $this->assertSame('0 B', $this->formatFileSize(0));
    }

    public function testFractionalKilobytes(): void
    {
        $this->assertSame('1.5 KB', $this->formatFileSize(1536));
    }
}
