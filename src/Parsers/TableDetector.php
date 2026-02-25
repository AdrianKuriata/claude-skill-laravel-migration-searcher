<?php

namespace DevSite\LaravelMigrationSearcher\Parsers;

use DevSite\LaravelMigrationSearcher\Contracts\ContentParser;

class TableDetector implements ContentParser
{
    public function parse(string $content): array
    {
        $tables = [];

        if (preg_match_all('/Schema::create\s*\(\s*[\'"]([^"\']+)[\'"]/', $content, $matches)) {
            foreach ($matches[1] as $table) {
                $tables[$table] = ['operation' => 'CREATE', 'methods' => []];
            }
        }

        if (preg_match_all('/Schema::table\s*\(\s*[\'"]([^"\']+)[\'"]/', $content, $matches)) {
            foreach ($matches[1] as $table) {
                if (!isset($tables[$table])) {
                    $tables[$table] = ['operation' => 'ALTER', 'methods' => []];
                }
            }
        }

        if (preg_match_all('/Schema::drop(?:IfExists)?\s*\(\s*[\'"]([^"\']+)[\'"]/', $content, $matches)) {
            foreach ($matches[1] as $table) {
                $tables[$table] = ['operation' => 'DROP', 'methods' => []];
            }
        }

        if (preg_match_all('/Schema::rename\s*\(\s*[\'"]([^"\']+)[\'"]/', $content, $matches)) {
            foreach ($matches[1] as $table) {
                $tables[$table] = ['operation' => 'RENAME', 'methods' => []];
            }
        }

        if (preg_match_all('/DB::table\s*\(\s*[\'"]([^"\']+)[\'"]/', $content, $matches)) {
            foreach ($matches[1] as $table) {
                if (!isset($tables[$table])) {
                    $tables[$table] = ['operation' => 'DATA', 'methods' => []];
                }
            }
        }

        return $tables;
    }
}
