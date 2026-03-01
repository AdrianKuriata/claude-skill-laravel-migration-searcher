<?php

namespace DevSite\LaravelMigrationSearcher\Renderers;

use DevSite\LaravelMigrationSearcher\Contracts\Renderers\MarkdownMigrationFormatter as MarkdownMigrationFormatterContract;
use DevSite\LaravelMigrationSearcher\Contracts\Renderers\Renderer;
use DevSite\LaravelMigrationSearcher\Contracts\Services\TextSanitizer;

class MarkdownRenderer implements Renderer
{
    public function __construct(
        private readonly MarkdownMigrationFormatterContract $formatter,
        private readonly TextSanitizer $sanitizer,
    ) {
    }

    public function getFileExtension(): string
    {
        return 'md';
    }

    public function renderFullIndex(array $data): string
    {
        $content = "# {$data['title']}\n\n";
        $content .= "**Generated:** {$data['generated_at']}\n";
        $content .= "**Number of migrations:** {$data['total_migrations']}\n\n";
        $content .= "---\n\n";

        foreach ($data['migrations'] as $migration) {
            $content .= $this->formatter->formatMigrationFull($migration);
            $content .= "\n---\n\n";
        }

        return $content;
    }

    public function renderByTypeIndex(array $data): string
    {
        $content = "# {$data['title']}\n\n";
        $content .= "**Generated:** {$data['generated_at']}\n\n";

        if (empty($data['groups'])) {
            $content .= "*No migrations found*\n\n";
            return $content;
        }

        foreach ($data['groups'] as $type => $group) {
            $safeType = $this->sanitizer->sanitize($type);

            $content .= "## {$safeType}\n\n";
            $content .= "**Count:** {$group['count']}\n\n";

            foreach ($group['migrations'] as $migration) {
                $content .= $this->formatter->formatMigrationCompact($migration);
                $content .= "\n";
            }

            $content .= "\n---\n\n";
        }

        return $content;
    }

    public function renderByTableIndex(array $data): string
    {
        $content = "# {$data['title']}\n\n";
        $content .= "**Generated:** {$data['generated_at']}\n\n";

        foreach ($data['tables'] as $table => $tableData) {
            $safeTable = $this->sanitizer->sanitize($table);
            $content .= "## Table: `{$safeTable}`\n\n";
            $content .= "**Number of migrations:** {$tableData['count']}\n\n";

            foreach ($tableData['migrations'] as $migration) {
                $safeFilename = $this->sanitizer->sanitize($migration['filename']);
                $safeOp = $this->sanitizer->sanitize($migration['table_operation']);
                $safeType = $this->sanitizer->sanitize($migration['type']);
                $safePath = $this->sanitizer->sanitize($migration['relative_path']);
                $safeTimestamp = $this->sanitizer->sanitize($migration['timestamp']);

                $content .= "### [{$safeOp}] {$safeFilename}\n\n";
                $content .= "- **Migration type:** {$safeType}\n";
                $content .= "- **Path:** `{$safePath}`\n";
                $content .= "- **Timestamp:** {$safeTimestamp}\n";

                if (!empty($migration['columns'])) {
                    $safeColumns = array_map([$this->sanitizer, 'sanitize'], array_keys($migration['columns']));
                    $content .= "- **Columns:** " . implode(', ', $safeColumns) . "\n";
                }

                if (!empty($migration['ddl_operations'])) {
                    $content .= "- **DDL Operations:** " . count($migration['ddl_operations']) . "\n";
                }

                if (!empty($migration['dml_operations'])) {
                    $content .= "- **DML Operations:** " . $this->formatter->formatDMLSummary($migration['dml_operations']) . "\n";
                }

                $content .= "- **Complexity:** {$migration['complexity']}/10\n";
                $content .= "\n";
            }

            $content .= "---\n\n";
        }

        return $content;
    }

    public function renderByOperationIndex(array $data): string
    {
        $content = "# {$data['title']}\n\n";
        $content .= "**Generated:** {$data['generated_at']}\n\n";

        foreach ($data['operations'] as $op => $opData) {
            $content .= "## {$opData['description']} ({$op})\n\n";
            $content .= "**Number of operations:** {$opData['count']}\n\n";

            if ($opData['count'] > 0) {
                foreach ($opData['migrations'] as $migration) {
                    $safeFilename = $this->sanitizer->sanitize($migration['filename']);
                    $safeTargetTable = $this->sanitizer->sanitize($migration['target_table']);
                    $safeType = $this->sanitizer->sanitize($migration['type']);
                    $safePath = $this->sanitizer->sanitize($migration['relative_path']);

                    $content .= "### {$safeFilename}\n\n";
                    $content .= "- **Table:** `{$safeTargetTable}`\n";
                    $content .= "- **Migration type:** {$safeType}\n";
                    $content .= "- **Path:** `{$safePath}`\n";

                    if ($op === 'ALTER' && !empty($migration['columns'])) {
                        $safeColumns = array_map([$this->sanitizer, 'sanitize'], array_keys($migration['columns']));
                        $content .= "- **Affected columns:** " . implode(', ', $safeColumns) . "\n";
                    }

                    if ($op === 'DATA' && !empty($migration['dml_operations'])) {
                        $content .= "- **DML Operations:**\n";
                        foreach ($migration['dml_operations'] as $dml) {
                            $safeDmlType = $this->sanitizer->sanitize($dml['type']);
                            $safeDmlTable = $this->sanitizer->sanitize($dml['table'] ?? $dml['model'] ?? 'unknown');
                            $content .= "  - **{$safeDmlType}** on `{$safeDmlTable}`";

                            if (!empty($dml['where_conditions'])) {
                                $safeConditions = array_map([$this->sanitizer, 'sanitize'], $dml['where_conditions']);
                                $content .= " WHERE: " . implode(' AND ', $safeConditions);
                            }
                            if (!empty($dml['columns_updated'])) {
                                $safeUpdated = array_map([$this->sanitizer, 'sanitize'], $dml['columns_updated']);
                                $content .= " (columns: " . implode(', ', $safeUpdated) . ")";
                            }
                            $content .= "\n";
                        }
                    }

                    $content .= "\n";
                }
            }

            $content .= "---\n\n";
        }

        $rawSql = $data['raw_sql'];
        $content .= "## Raw SQL\n\n";
        $content .= "**Number of migrations with raw SQL:** {$rawSql['count']}\n\n";

        foreach ($rawSql['migrations'] as $migration) {
            $safeFilename = $this->sanitizer->sanitize($migration['filename']);
            $safeType = $this->sanitizer->sanitize($migration['type']);
            $safePath = $this->sanitizer->sanitize($migration['relative_path']);

            $content .= "### {$safeFilename}\n\n";
            $content .= "- **Migration type:** {$safeType}\n";
            $content .= "- **Path:** `{$safePath}`\n";
            $content .= "- **Number of statements:** " . count($migration['raw_sql']) . "\n\n";

            foreach ($migration['raw_sql'] as $sql) {
                $safeOperation = $this->sanitizer->sanitize($sql['operation'] ?? 'unknown');
                $safeSqlType = $this->sanitizer->sanitize($sql['type']);
                $safeSql = $this->sanitizer->sanitize($sql['sql']);
                $content .= "**[{$safeOperation}]** ({$safeSqlType}):\n";
                $content .= "```sql\n{$safeSql}\n```\n\n";
            }
        }

        return $content;
    }

    public function renderStats(array $data): string
    {
        $content = "# Migration Statistics\n\n";
        $content .= "**Generated:** {$data['generated_at']}\n";
        $content .= "**Total migrations:** {$data['total_migrations']}\n\n";

        $content .= "## By Type\n\n";
        if (!empty($data['by_type'])) {
            foreach ($data['by_type'] as $type => $count) {
                $safeType = $this->sanitizer->sanitize($type);
                $content .= "- **{$safeType}:** {$count}\n";
            }
        }
        $content .= "\n";

        $content .= "## Complexity\n\n";
        $content .= "- **Average:** {$data['complexity']['average']}\n";
        $content .= "- **Max:** {$data['complexity']['max']}\n";
        $content .= "- **High complexity (>=7):** {$data['complexity']['high_complexity']}\n\n";

        $content .= "## Data\n\n";
        $content .= "- **Data modifications:** {$data['data_modifications']}\n";
        $content .= "- **Raw SQL migrations:** {$data['raw_sql_count']}\n\n";

        if (!empty($data['tables'])) {
            $content .= "## Tables (top " . count($data['tables']) . ")\n\n";
            foreach ($data['tables'] as $table => $info) {
                $ops = implode(', ', array_map(
                    fn ($op, $count) => "{$this->sanitizer->sanitize($op)}: {$count}",
                    array_keys($info['operations']),
                    array_values($info['operations'])
                ));
                $safeTable = $this->sanitizer->sanitize($table);
                $content .= "- **`{$safeTable}`** — {$info['migrations_count']} migrations ({$ops})\n";
            }
            $content .= "\n";
        }

        return $content;
    }
}
