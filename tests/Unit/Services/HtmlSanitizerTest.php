<?php

namespace Tests\Unit\Services;

use DevSite\LaravelMigrationSearcher\Contracts\Services\TextSanitizer;
use DevSite\LaravelMigrationSearcher\Services\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    protected HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizer();
    }

    public function testImplementsTextSanitizerContract(): void
    {
        $this->assertInstanceOf(TextSanitizer::class, $this->sanitizer);
    }

    public function testSanitizePreventsHtmlInjection(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            $this->sanitizer->sanitize('<script>alert(1)</script>')
        );
    }

    public function testSanitizeEscapesAngleBrackets(): void
    {
        $this->assertSame(
            'Eloquent-&gt;save()',
            $this->sanitizer->sanitize('Eloquent->save()')
        );
    }

    public function testSanitizeEscapesQuotes(): void
    {
        $this->assertSame(
            '&quot;value&quot; &#039;attr&#039;',
            $this->sanitizer->sanitize('"value" \'attr\'')
        );
    }

    public function testSanitizeEscapesAmpersand(): void
    {
        $this->assertSame(
            'foo &amp; bar',
            $this->sanitizer->sanitize('foo & bar')
        );
    }

    public function testSanitizeHandlesEmptyString(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize(''));
    }

    public function testSanitizeHandlesPlainText(): void
    {
        $this->assertSame('hello world', $this->sanitizer->sanitize('hello world'));
    }

    public function testSanitizeHandlesInvalidUtf8(): void
    {
        $result = $this->sanitizer->sanitize("test\xC0\xAFvalue");
        $this->assertIsString($result);
        $this->assertStringContainsString('test', $result);
    }
}
