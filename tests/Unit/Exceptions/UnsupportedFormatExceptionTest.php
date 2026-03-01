<?php

namespace Tests\Unit\Exceptions;

use DevSite\LaravelMigrationSearcher\Exceptions\UnsupportedFormatException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UnsupportedFormatExceptionTest extends TestCase
{
    #[Test]
    public function itCreatesWithFormatAndAvailableList(): void
    {
        $exception = UnsupportedFormatException::create('yaml', ['markdown', 'json']);

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertStringContainsString('yaml', $exception->getMessage());
        $this->assertStringContainsString('markdown, json', $exception->getMessage());
    }
}
