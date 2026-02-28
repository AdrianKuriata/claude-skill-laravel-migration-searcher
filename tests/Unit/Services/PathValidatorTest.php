<?php

namespace Tests\Unit\Services;

use DevSite\LaravelMigrationSearcher\Services\PathValidator;
use PHPUnit\Framework\TestCase;

class PathValidatorTest extends TestCase
{
    protected PathValidator $validator;
    protected string $basePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->basePath = sys_get_temp_dir() . '/path-validator-test-' . uniqid();
        mkdir($this->basePath, 0755, true);
        $this->validator = new PathValidator($this->basePath);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->basePath)) {
            rmdir($this->basePath);
        }
        parent::tearDown();
    }

    // ── normalize ──────────────────────────────────────────────────

    public function testNormalizeAbsolutePath(): void
    {
        $this->assertSame('/foo/bar', $this->validator->normalize('/foo/bar'));
    }

    public function testNormalizeRelativePath(): void
    {
        $this->assertSame('foo/bar', $this->validator->normalize('foo/bar'));
    }

    public function testNormalizeRemovesDoubleDots(): void
    {
        $this->assertSame('/foo/baz', $this->validator->normalize('/foo/bar/../baz'));
    }

    public function testNormalizeRemovesSingleDots(): void
    {
        $this->assertSame('/foo/bar', $this->validator->normalize('/foo/./bar'));
    }

    public function testNormalizeMultipleDoubleDots(): void
    {
        $this->assertSame('/baz', $this->validator->normalize('/foo/bar/../../baz'));
    }

    public function testNormalizeDoubleDotsBeyondRoot(): void
    {
        $this->assertSame('../baz', $this->validator->normalize('foo/../../baz'));
    }

    // ── isWithinBasePath ───────────────────────────────────────────

    public function testValidPathWithinBase(): void
    {
        $path = $this->basePath . '/output';
        $this->assertTrue($this->validator->isWithinBasePath($path));
    }

    public function testTraversalAttackBlocked(): void
    {
        $path = $this->basePath . '/a/b/../../../../tmp/evil';
        $this->assertFalse($this->validator->isWithinBasePath($path));
    }

    public function testAbsolutePathOutsideBase(): void
    {
        $path = '/tmp/outside-base-' . uniqid() . '/output';
        $this->assertFalse($this->validator->isWithinBasePath($path));
    }

    public function testNestedNonExistentPathWithinBase(): void
    {
        $path = $this->basePath . '/nonexistent/deep/nested/output';
        $this->assertTrue($this->validator->isWithinBasePath($path));
    }

    public function testRootGuardPreventsInfiniteLoop(): void
    {
        $path = '/nonexistent-' . uniqid() . '/nonexistent/output';
        $this->assertFalse($this->validator->isWithinBasePath($path));
    }
}
