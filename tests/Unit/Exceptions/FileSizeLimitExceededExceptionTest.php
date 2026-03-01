<?php

namespace Tests\Unit\Exceptions;

use DevSite\LaravelMigrationSearcher\Exceptions\FileSizeLimitExceededException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FileSizeLimitExceededExceptionTest extends TestCase
{
    #[Test]
    public function itCreatesWithGenericMessage(): void
    {
        $exception = FileSizeLimitExceededException::create(10000000, 5242880);

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('File exceeds maximum allowed size', $exception->getMessage());
    }

    #[Test]
    public function itDoesNotLeakFileSizeDetails(): void
    {
        $exception = FileSizeLimitExceededException::create(10000000, 5242880);

        $this->assertStringNotContainsString('10000000', $exception->getMessage());
        $this->assertStringNotContainsString('5242880', $exception->getMessage());
    }
}
