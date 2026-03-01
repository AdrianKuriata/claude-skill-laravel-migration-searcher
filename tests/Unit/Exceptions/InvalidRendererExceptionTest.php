<?php

namespace Tests\Unit\Exceptions;

use DevSite\LaravelMigrationSearcher\Exceptions\InvalidRendererException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InvalidRendererExceptionTest extends TestCase
{
    #[Test]
    public function itCreatesWithGenericMessage(): void
    {
        $exception = InvalidRendererException::notImplementingContract('App\\BadRenderer');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Renderer class must implement the Renderer contract', $exception->getMessage());
    }

    #[Test]
    public function itDoesNotLeakClassName(): void
    {
        $exception = InvalidRendererException::notImplementingContract('App\\BadRenderer');

        $this->assertStringNotContainsString('App\\BadRenderer', $exception->getMessage());
    }
}
