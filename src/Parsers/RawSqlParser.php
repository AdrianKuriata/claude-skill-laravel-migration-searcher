<?php

namespace DevSite\LaravelMigrationSearcher\Parsers;

use DevSite\LaravelMigrationSearcher\Contracts\Parsers\RawSqlParser as RawSqlParserContract;
use DevSite\LaravelMigrationSearcher\DTOs\RawSqlStatement;
use DevSite\LaravelMigrationSearcher\Enums\RawSqlType;
use DevSite\LaravelMigrationSearcher\Enums\SqlOperationType;

class RawSqlParser implements RawSqlParserContract
{
    /** @return RawSqlStatement[] */
    public function parse(string $content): array
    {
        return $this->extractRawSQL($content);
    }

    /** @return RawSqlStatement[] */
    public function extractRawSQL(string $content): array
    {
        $sql = [];

        if (preg_match_all('/DB::statement\s*\(\s*(["\'])(.+?)\1\s*(?:,|\))/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statement = $match[2];
                $sql[] = new RawSqlStatement(
                    RawSqlType::STATEMENT,
                    $this->formatSQL($statement),
                    $this->detectSQLOperation($statement),
                );
            }
        }

        if (preg_match_all('/DB::unprepared\s*\(\s*(["\'])(.+?)\1\s*(?:,|\))/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statement = $match[2];
                $sql[] = new RawSqlStatement(
                    RawSqlType::UNPREPARED,
                    $this->formatSQL($statement),
                    $this->detectSQLOperation($statement),
                );
            }
        }

        if (preg_match_all('/DB::raw\s*\(\s*(["\'])(.+?)\1\s*\)/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statement = $match[2];
                $sql[] = new RawSqlStatement(
                    RawSqlType::RAW,
                    $this->formatSQL($statement),
                    SqlOperationType::EXPRESSION,
                );
            }
        }

        if (preg_match_all('/<<<(["\']?)SQL\1\s*(.+?)\s*SQL/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statement = $match[2];
                $sql[] = new RawSqlStatement(
                    RawSqlType::HEREDOC,
                    $this->formatSQL($statement),
                    $this->detectSQLOperation($statement),
                );
            }
        }

        return $sql;
    }

    public function formatSQL(string $sql): string
    {
        $sql = trim($sql);
        $sql = preg_replace('/\s+/', ' ', $sql);

        if (strlen($sql) > 500) {
            $sql = substr($sql, 0, 500) . '... [truncated]';
        }

        return $sql;
    }

    public function detectSQLOperation(string $sql): SqlOperationType
    {
        $sql = strtoupper(trim($sql));

        if (str_starts_with($sql, 'SELECT')) {
            return SqlOperationType::SELECT;
        }
        if (str_starts_with($sql, 'INSERT')) {
            return SqlOperationType::INSERT;
        }
        if (str_starts_with($sql, 'UPDATE')) {
            return SqlOperationType::UPDATE;
        }
        if (str_starts_with($sql, 'DELETE')) {
            return SqlOperationType::DELETE;
        }
        if (str_starts_with($sql, 'CREATE')) {
            return SqlOperationType::CREATE;
        }
        if (str_starts_with($sql, 'ALTER')) {
            return SqlOperationType::ALTER;
        }
        if (str_starts_with($sql, 'DROP')) {
            return SqlOperationType::DROP;
        }
        if (str_starts_with($sql, 'TRUNCATE')) {
            return SqlOperationType::TRUNCATE;
        }

        return SqlOperationType::OTHER;
    }
}
