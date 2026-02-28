<?php

namespace DevSite\LaravelMigrationSearcher\Renderers;

use DevSite\LaravelMigrationSearcher\Contracts\Renderer;

class MarkdownRenderer implements Renderer
{
    private MarkdownMigrationFormatter $formatter;

    public function __construct(?MarkdownMigrationFormatter $formatter = null)
    {
        $this->formatter = $formatter ?? new MarkdownMigrationFormatter();
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
            $safeType = $this->formatter->escapeHtml($type);

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
            $safeTable = $this->formatter->escapeHtml($table);
            $content .= "## Table: `{$safeTable}`\n\n";
            $content .= "**Number of migrations:** {$tableData['count']}\n\n";

            foreach ($tableData['migrations'] as $migration) {
                $safeFilename = $this->formatter->escapeHtml($migration['filename']);
                $safeOp = $this->formatter->escapeHtml($migration['table_operation']);
                $safeType = $this->formatter->escapeHtml($migration['type']);
                $safePath = $this->formatter->escapeHtml($migration['relative_path']);
                $safeTimestamp = $this->formatter->escapeHtml($migration['timestamp']);

                $content .= "### [{$safeOp}] {$safeFilename}\n\n";
                $content .= "- **Migration type:** {$safeType}\n";
                $content .= "- **Path:** `{$safePath}`\n";
                $content .= "- **Timestamp:** {$safeTimestamp}\n";

                if (!empty($migration['columns'])) {
                    $safeColumns = array_map([$this->formatter, 'escapeHtml'], array_keys($migration['columns']));
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
                    $safeFilename = $this->formatter->escapeHtml($migration['filename']);
                    $safeTargetTable = $this->formatter->escapeHtml($migration['target_table']);
                    $safeType = $this->formatter->escapeHtml($migration['type']);
                    $safePath = $this->formatter->escapeHtml($migration['relative_path']);

                    $content .= "### {$safeFilename}\n\n";
                    $content .= "- **Table:** `{$safeTargetTable}`\n";
                    $content .= "- **Migration type:** {$safeType}\n";
                    $content .= "- **Path:** `{$safePath}`\n";

                    if ($op === 'ALTER' && !empty($migration['columns'])) {
                        $safeColumns = array_map([$this->formatter, 'escapeHtml'], array_keys($migration['columns']));
                        $content .= "- **Affected columns:** " . implode(', ', $safeColumns) . "\n";
                    }

                    if ($op === 'DATA' && !empty($migration['dml_operations'])) {
                        $content .= "- **DML Operations:**\n";
                        foreach ($migration['dml_operations'] as $dml) {
                            $safeDmlType = $this->formatter->escapeHtml($dml['type']);
                            $safeDmlTable = $this->formatter->escapeHtml($dml['table'] ?? $dml['model'] ?? 'unknown');
                            $content .= "  - **{$safeDmlType}** on `{$safeDmlTable}`";

                            if (!empty($dml['where_conditions'])) {
                                $safeConditions = array_map([$this->formatter, 'escapeHtml'], $dml['where_conditions']);
                                $content .= " WHERE: " . implode(' AND ', $safeConditions);
                            }
                            if (!empty($dml['columns_updated'])) {
                                $safeUpdated = array_map([$this->formatter, 'escapeHtml'], $dml['columns_updated']);
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
            $safeFilename = $this->formatter->escapeHtml($migration['filename']);
            $safeType = $this->formatter->escapeHtml($migration['type']);
            $safePath = $this->formatter->escapeHtml($migration['relative_path']);

            $content .= "### {$safeFilename}\n\n";
            $content .= "- **Migration type:** {$safeType}\n";
            $content .= "- **Path:** `{$safePath}`\n";
            $content .= "- **Number of statements:** " . count($migration['raw_sql']) . "\n\n";

            foreach ($migration['raw_sql'] as $sql) {
                $safeOperation = $this->formatter->escapeHtml($sql['operation'] ?? 'unknown');
                $safeSqlType = $this->formatter->escapeHtml($sql['type']);
                $safeSql = $this->formatter->escapeHtml($sql['sql']);
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
                $content .= "- **{$type}:** {$count}\n";
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
                    fn ($op, $count) => "{$op}: {$count}",
                    array_keys($info['operations']),
                    array_values($info['operations'])
                ));
                $content .= "- **`{$table}`** — {$info['migrations_count']} migrations ({$ops})\n";
            }
            $content .= "\n";
        }

        return $content;
    }
}
