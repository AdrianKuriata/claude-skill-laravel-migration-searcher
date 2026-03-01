<?php

namespace Tests\Unit\Exceptions;

use DevSite\LaravelMigrationSearcher\Exceptions\InvalidPathException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InvalidPathExceptionTest extends TestCase
{
    #[Test]
    public function itCreatesForInvalidBasePath(): void
    {
        $exception = InvalidPathException::invalidBasePath('/nonexistent/path');

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertSame('Base path must be a valid directory.', $exception->getMessage());
        $this->assertStringNotContainsString('/nonexistent/path', $exception->getMessage());
    }
}
