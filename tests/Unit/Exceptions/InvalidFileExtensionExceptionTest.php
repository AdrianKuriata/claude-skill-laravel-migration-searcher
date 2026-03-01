<?php

namespace Tests\Unit\Exceptions;

use DevSite\LaravelMigrationSearcher\Exceptions\InvalidFileExtensionException;
use PHPUnit\Framework\TestCase;

class InvalidFileExtensionExceptionTest extends TestCase
{
    public function testCreateReturnsException(): void
    {
        $exception = InvalidFileExtensionException::create();

        $this->assertInstanceOf(InvalidFileExtensionException::class, $exception);
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    public function testMessageDoesNotExposeFilePath(): void
    {
        $exception = InvalidFileExtensionException::create();

        $this->assertStringNotContainsString('/', $exception->getMessage());
    }
}
