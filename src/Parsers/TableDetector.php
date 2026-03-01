<?php

namespace DevSite\LaravelMigrationSearcher\Parsers;

use DevSite\LaravelMigrationSearcher\Contracts\Parsers\TableDetector as TableDetectorContract;
use DevSite\LaravelMigrationSearcher\DTOs\TableInfo;
use DevSite\LaravelMigrationSearcher\Enums\TableOperation;

class TableDetector implements TableDetectorContract
{
    /** @return array<string, TableInfo> */
    public function parse(string $content): array
    {
        $tables = [];

        if (preg_match_all('/Schema::create\s*\(\s*[\'"]([^"\']+)[\'"]/', $content, $matches)) {
            foreach ($matches[1] as $table) {
                $tables[$table] = new TableInfo(TableOperation::CREATE, []);
            }
        }

        if (preg_match_all('/Schema::table\s*\(\s*[\'"]([^"\']+)[\'"]/', $content, $matches)) {
            foreach ($matches[1] as $table) {
                if (!isset($tables[$table])) {
                    $tables[$table] = new TableInfo(TableOperation::ALTER, []);
                }
            }
        }

        if (preg_match_all('/Schema::drop(?:IfExists)?\s*\(\s*[\'"]([^"\']+)[\'"]/', $content, $matches)) {
            foreach ($matches[1] as $table) {
                $tables[$table] = new TableInfo(TableOperation::DROP, []);
            }
        }

        if (preg_match_all('/Schema::rename\s*\(\s*[\'"]([^"\']+)[\'"]/', $content, $matches)) {
            foreach ($matches[1] as $table) {
                $tables[$table] = new TableInfo(TableOperation::RENAME, []);
            }
        }

        if (preg_match_all('/DB::table\s*\(\s*[\'"]([^"\']+)[\'"]/', $content, $matches)) {
            foreach ($matches[1] as $table) {
                if (!isset($tables[$table])) {
                    $tables[$table] = new TableInfo(TableOperation::DATA, []);
                }
            }
        }

        return $tables;
    }
}
