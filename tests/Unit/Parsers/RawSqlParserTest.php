<?php

namespace Tests\Unit\Parsers;

use DevSite\LaravelMigrationSearcher\DTOs\RawSqlStatement;
use DevSite\LaravelMigrationSearcher\Enums\RawSqlType;
use DevSite\LaravelMigrationSearcher\Enums\SqlOperationType;
use DevSite\LaravelMigrationSearcher\Parsers\RawSqlParser;
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
        $this->assertInstanceOf(RawSqlStatement::class, $sql[0]);
        $this->assertSame(RawSqlType::STATEMENT, $sql[0]->type);
        $this->assertSame(SqlOperationType::CREATE, $sql[0]->operation);
    }

    public function testExtractsDbUnprepared(): void
    {
        $content = 'DB::unprepared("ALTER TABLE users ADD COLUMN age INT");';
        $sql = $this->parser->extractRawSQL($content);

        $this->assertCount(1, $sql);
        $this->assertSame(RawSqlType::UNPREPARED, $sql[0]->type);
        $this->assertSame(SqlOperationType::ALTER, $sql[0]->operation);
    }

    public function testExtractsDbRaw(): void
    {
        $content = 'DB::raw("COALESCE(name, email)")';
        $sql = $this->parser->extractRawSQL($content);

        $this->assertCount(1, $sql);
        $this->assertSame(RawSqlType::RAW, $sql[0]->type);
        $this->assertSame(SqlOperationType::EXPRESSION, $sql[0]->operation);
    }

    public function testExtractsHeredocSql(): void
    {
        $content = "DB::unprepared(<<<SQL\nSELECT * FROM users\nSQL\n);";
        $sql = $this->parser->extractRawSQL($content);

        $heredocs = array_filter($sql, fn (RawSqlStatement $s) => $s->type === RawSqlType::HEREDOC);
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
            'SELECT * FROM users' => SqlOperationType::SELECT,
            'INSERT INTO users VALUES (1)' => SqlOperationType::INSERT,
            'UPDATE users SET name = "a"' => SqlOperationType::UPDATE,
            'DELETE FROM users WHERE id = 1' => SqlOperationType::DELETE,
            'CREATE TABLE foo (id INT)' => SqlOperationType::CREATE,
            'ALTER TABLE foo ADD col INT' => SqlOperationType::ALTER,
            'DROP TABLE foo' => SqlOperationType::DROP,
            'TRUNCATE TABLE foo' => SqlOperationType::TRUNCATE,
            'GRANT ALL ON foo TO bar' => SqlOperationType::OTHER,
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
