<?php

namespace DevSite\LaravelMigrationSearcher\Services\Parsers;

use DevSite\LaravelMigrationSearcher\Contracts\ContentParserInterface;

class RawSqlParser implements ContentParserInterface
{
    public function parse(string $content): array
    {
        return $this->extractRawSQL($content);
    }

    public function extractRawSQL(string $content): array
    {
        $sql = [];

        if (preg_match_all('/DB::statement\s*\(\s*(["\'])(.+?)\1\s*(?:,|\))/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statement = $match[2];
                $sql[] = [
                    'type' => 'statement',
                    'sql' => $this->formatSQL($statement),
                    'operation' => $this->detectSQLOperation($statement),
                ];
            }
        }

        if (preg_match_all('/DB::unprepared\s*\(\s*(["\'])(.+?)\1\s*(?:,|\))/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statement = $match[2];
                $sql[] = [
                    'type' => 'unprepared',
                    'sql' => $this->formatSQL($statement),
                    'operation' => $this->detectSQLOperation($statement),
                ];
            }
        }

        if (preg_match_all('/DB::raw\s*\(\s*(["\'])(.+?)\1\s*\)/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statement = $match[2];
                $sql[] = [
                    'type' => 'raw',
                    'sql' => $this->formatSQL($statement),
                    'operation' => 'EXPRESSION',
                ];
            }
        }

        if (preg_match_all('/<<<(["\']?)SQL\1\s*(.+?)\s*SQL/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statement = $match[2];
                $sql[] = [
                    'type' => 'heredoc',
                    'sql' => $this->formatSQL($statement),
                    'operation' => $this->detectSQLOperation($statement),
                ];
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

    public function detectSQLOperation(string $sql): string
    {
        $sql = strtoupper(trim($sql));

        if (strpos($sql, 'SELECT') === 0) return 'SELECT';
        if (strpos($sql, 'INSERT') === 0) return 'INSERT';
        if (strpos($sql, 'UPDATE') === 0) return 'UPDATE';
        if (strpos($sql, 'DELETE') === 0) return 'DELETE';
        if (strpos($sql, 'CREATE') === 0) return 'CREATE';
        if (strpos($sql, 'ALTER') === 0) return 'ALTER';
        if (strpos($sql, 'DROP') === 0) return 'DROP';
        if (strpos($sql, 'TRUNCATE') === 0) return 'TRUNCATE';

        return 'OTHER';
    }
}
