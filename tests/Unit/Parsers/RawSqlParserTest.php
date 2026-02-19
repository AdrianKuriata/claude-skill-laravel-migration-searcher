<?php

namespace Tests\Unit\Parsers;

use DevSite\LaravelMigrationSearcher\Services\Parsers\RawSqlParser;
use PHPUnit\Framework\TestCase;

class RawSqlParserTest extends TestCase
{
    protected RawSqlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new RawSqlParser();
    }

    public function testParseDelegatesToExtractRawSQL(): void
    {
        $content = '<?php' . "\n";
        $this->assertSame(
            $this->parser->extractRawSQL($content),
            $this->parser->parse($content)
        );
    }

    public function testExtractsDbStatement(): void
    {
        $content = 'DB::statement("CREATE INDEX idx ON users (email)");';
        $sql = $this->parser->extractRawSQL($content);

        $this->assertCount(1, $sql);
        $this->assertSame('statement', $sql[0]['type']);
        $this->assertSame('CREATE', $sql[0]['operation']);
    }

    public function testExtractsDbUnprepared(): void
    {
        $content = 'DB::unprepared("ALTER TABLE users ADD COLUMN age INT");';
        $sql = $this->parser->extractRawSQL($content);

        $this->assertCount(1, $sql);
        $this->assertSame('unprepared', $sql[0]['type']);
        $this->assertSame('ALTER', $sql[0]['operation']);
    }

    public function testExtractsDbRaw(): void
    {
        $content = 'DB::raw("COALESCE(name, email)")';
        $sql = $this->parser->extractRawSQL($content);

        $this->assertCount(1, $sql);
        $this->assertSame('raw', $sql[0]['type']);
        $this->assertSame('EXPRESSION', $sql[0]['operation']);
    }

    public function testExtractsHeredocSql(): void
    {
        $content = "DB::unprepared(<<<SQL\nSELECT * FROM users\nSQL\n);";
        $sql = $this->parser->extractRawSQL($content);

        $heredocs = array_filter($sql, fn($s) => $s['type'] === 'heredoc');
        $this->assertNotEmpty($heredocs);
    }

    public function testFormatSqlTrimsAndNormalizesWhitespace(): void
    {
        $this->assertSame('SELECT * FROM users', $this->parser->formatSQL("  SELECT  *  FROM   users  "));
    }

    public function testFormatSqlTruncatesLongStatements(): void
    {
        $longSql = 'SELECT ' . str_repeat('column_name, ', 100) . 'id FROM users';
        $result = $this->parser->formatSQL($longSql);

        $this->assertStringEndsWith('... [truncated]', $result);
        $this->assertLessThanOrEqual(520, strlen($result));
    }

    public function testDetectSQLOperations(): void
    {
        $expectations = [
            'SELECT * FROM users' => 'SELECT',
            'INSERT INTO users VALUES (1)' => 'INSERT',
            'UPDATE users SET name = "a"' => 'UPDATE',
            'DELETE FROM users WHERE id = 1' => 'DELETE',
            'CREATE TABLE foo (id INT)' => 'CREATE',
            'ALTER TABLE foo ADD col INT' => 'ALTER',
            'DROP TABLE foo' => 'DROP',
            'TRUNCATE TABLE foo' => 'TRUNCATE',
            'GRANT ALL ON foo TO bar' => 'OTHER',
        ];

        foreach ($expectations as $sql => $expected) {
            $this->assertSame($expected, $this->parser->detectSQLOperation($sql), "Failed for SQL: {$sql}");
        }
    }

    public function testEmptyContentReturnsNoSql(): void
    {
        $this->assertEmpty($this->parser->extractRawSQL("<?php\n"));
    }
}
