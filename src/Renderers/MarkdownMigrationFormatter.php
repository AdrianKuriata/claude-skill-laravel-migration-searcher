<?php

namespace DevSite\LaravelMigrationSearcher\Renderers;

use DevSite\LaravelMigrationSearcher\Contracts\Renderers\MarkdownMigrationFormatter as MarkdownMigrationFormatterContract;
use DevSite\LaravelMigrationSearcher\Contracts\Services\TextSanitizer;

class MarkdownMigrationFormatter implements MarkdownMigrationFormatterContract
{
    public function __construct(
        private readonly TextSanitizer $sanitizer,
    ) {
    }

    public function formatMigrationFull(array $migration): string
    {
        $content = $this->formatHeader($migration);
        $content .= $this->formatTables($migration);
        $content .= $this->formatColumns($migration);
        $content .= $this->formatDdlOperations($migration);
        $content .= $this->formatDmlOperations($migration);
        $content .= $this->formatRawSql($migration);
        $content .= $this->formatForeignKeys($migration);
        $content .= $this->formatIndexes($migration);
        $content .= $this->formatDependencies($migration);

        return $content;
    }

    public function formatMigrationCompact(array $migration): string
    {
        $safeFilename = $this->sanitizer->sanitize($migration['filename']);
        $content = "### {$safeFilename}\n\n";

        $tables = !empty($migration['tables'])
            ? implode(', ', array_map([$this->sanitizer, 'sanitize'], array_keys($migration['tables'])))
            : 'none';
        $content .= "**Tables:** {$tables}  \n";

        if (!empty($migration['columns'])) {
            $safeColumns = array_map([$this->sanitizer, 'sanitize'], array_keys($migration['columns']));
            $content .= "**Columns:** " . implode(', ', $safeColumns) . "  \n";
        }

        if ($migration['has_data_modifications']) {
            $content .= "**⚠️ Modifies data**  \n";
        }

        $content .= "**Complexity:** {$migration['complexity']}/10  \n";

        return $content;
    }

    public function formatDMLSummary(array $dmlOperations): string
    {
        $summary = collect($dmlOperations)->groupBy('type')->map(fn ($ops) => count($ops));
        $parts = [];
        foreach ($summary as $type => $count) {
            $parts[] = "{$type}: {$count}";
        }

        return implode(', ', $parts);
    }

    protected function formatHeader(array $migration): string
    {
        $safeFilename = $this->sanitizer->sanitize($migration['filename']);
        $safeType = $this->sanitizer->sanitize($migration['type']);
        $safePath = $this->sanitizer->sanitize($migration['relative_path']);
        $safeTimestamp = $this->sanitizer->sanitize($migration['timestamp']);
        $safeName = $this->sanitizer->sanitize($migration['name']);

        $content = "### {$safeFilename}\n\n";
        $content .= "**Type:** {$safeType}  \n";
        $content .= "**Path:** `{$safePath}`  \n";
        $content .= "**Timestamp:** {$safeTimestamp}  \n";
        $content .= "**Name:** {$safeName}  \n";
        $content .= "**Complexity:** {$migration['complexity']}/10  \n\n";

        return $content;
    }

    protected function formatTables(array $migration): string
    {
        if (empty($migration['tables'])) {
            return '';
        }

        $content = "**Tables:**\n";
        foreach ($migration['tables'] as $table => $info) {
            $safeTable = $this->sanitizer->sanitize($table);
            $safeOp = $this->sanitizer->sanitize($info['operation']);
            $content .= "- `{$safeTable}` ({$safeOp})\n";
        }

        return $content . "\n";
    }

    protected function formatColumns(array $migration): string
    {
        if (empty($migration['columns'])) {
            return '';
        }

        $content = "**Columns:**\n";
        foreach ($migration['columns'] as $column => $info) {
            $safeColumn = $this->sanitizer->sanitize($column);
            $safeColType = $this->sanitizer->sanitize($info['type']);
            $modifiers = !empty($info['modifiers'])
                ? ' [' . implode(', ', array_map([$this->sanitizer, 'sanitize'], $info['modifiers'])) . ']'
                : '';
            $content .= "- `{$safeColumn}` ({$safeColType}{$modifiers})\n";
        }

        return $content . "\n";
    }

    protected function formatDdlOperations(array $migration): string
    {
        if (empty($migration['ddl_operations'])) {
            return '';
        }

        $content = "**DDL Operations:**\n";
        $grouped = collect($migration['ddl_operations'])->groupBy('category');
        foreach ($grouped as $category => $ops) {
            $safeCategory = $this->sanitizer->sanitize($category);
            $content .= "- **{$safeCategory}:** " . count($ops) . " operations\n";
        }

        return $content . "\n";
    }

    protected function formatDmlOperations(array $migration): string
    {
        if (empty($migration['dml_operations'])) {
            return '';
        }

        $content = "**DML Operations:**\n";
        foreach ($migration['dml_operations'] as $dml) {
            $content .= $this->formatSingleDmlOperation($dml);
        }

        return $content . "\n";
    }

    protected function formatSingleDmlOperation(array $dml): string
    {
        $safeDmlType = $this->sanitizer->sanitize($dml['type']);
        $content = '';

        if (isset($dml['table'])) {
            $content .= $this->formatTableDmlOperation($dml, $safeDmlType);
        } elseif (isset($dml['model'])) {
            $content .= $this->formatModelDmlOperation($dml, $safeDmlType);
        } elseif (isset($dml['variable'])) {
            $content .= $this->formatVariableDmlOperation($dml, $safeDmlType);
        } elseif ($dml['type'] === 'LOOP') {
            $content .= $this->formatLoopDmlOperation($dml);
        }

        $content .= "\n";

        return $content;
    }

    protected function formatTableDmlOperation(array $dml, string $safeDmlType): string
    {
        $safeDmlTable = $this->sanitizer->sanitize($dml['table']);
        $content = "- **{$safeDmlType}** on `{$safeDmlTable}`";

        if (!empty($dml['where_conditions'])) {
            $safeConditions = array_map([$this->sanitizer, 'sanitize'], $dml['where_conditions']);
            $content .= "\n  - WHERE: " . implode(' AND ', $safeConditions);
        }

        if (!empty($dml['columns_updated'])) {
            $safeUpdated = array_map([$this->sanitizer, 'sanitize'], $dml['columns_updated']);
            $content .= "\n  - Columns: " . implode(', ', $safeUpdated);
        }

        if (!empty($dml['has_db_raw']) && !empty($dml['db_raw_expressions'])) {
            $content .= "\n  - **⚠️ Uses DB::raw:**";
            foreach ($dml['db_raw_expressions'] as $rawExpr) {
                $preview = strlen($rawExpr) > 100 ? substr($rawExpr, 0, 100) . '...' : $rawExpr;
                $safePreview = $this->sanitizer->sanitize($preview);
                $content .= "\n    ```sql\n    {$safePreview}\n    ```";
            }
        }

        if (!empty($dml['data_preview']) && empty($dml['has_db_raw'])) {
            $safeDataPreview = $this->sanitizer->sanitize($dml['data_preview']);
            $content .= "\n  - Data: " . $safeDataPreview;
        }

        return $content;
    }

    protected function formatModelDmlOperation(array $dml, string $safeDmlType): string
    {
        $safeModel = $this->sanitizer->sanitize($dml['model']);
        $safeMethod = $this->sanitizer->sanitize($dml['method'] ?? 'unknown');
        $content = "- **{$safeDmlType}** via `{$safeModel}::{$safeMethod}`";

        if (!empty($dml['note'])) {
            $content .= "\n  - " . $this->sanitizer->sanitize($dml['note']);
        }

        return $content;
    }

    protected function formatVariableDmlOperation(array $dml, string $safeDmlType): string
    {
        $safeVariable = $this->sanitizer->sanitize($dml['variable']);
        $safeMethod = $this->sanitizer->sanitize($dml['method'] ?? 'unknown');
        $content = "- **{$safeDmlType}** via `{$safeVariable}->{$safeMethod}`";

        if (!empty($dml['relation'])) {
            $content .= " (relation: " . $this->sanitizer->sanitize($dml['relation']) . ")";
        }

        if (!empty($dml['note'])) {
            $content .= "\n  - " . $this->sanitizer->sanitize($dml['note']);
        }

        return $content;
    }

    protected function formatLoopDmlOperation(array $dml): string
    {
        $safeMethod = $this->sanitizer->sanitize($dml['method'] ?? 'unknown');
        $content = "- **🔁 LOOP** ({$safeMethod})";

        if (!empty($dml['operations_in_loop'])) {
            $safeOps = array_map([$this->sanitizer, 'sanitize'], $dml['operations_in_loop']);
            $content .= "\n  - Operations: " . implode(', ', $safeOps);
        }

        if (!empty($dml['note'])) {
            $content .= "\n  - " . $this->sanitizer->sanitize($dml['note']);
        }

        return $content;
    }

    protected function formatRawSql(array $migration): string
    {
        if (empty($migration['raw_sql'])) {
            return '';
        }

        $content = "**Raw SQL:** " . count($migration['raw_sql']) . " statement(s)\n\n";
        foreach ($migration['raw_sql'] as $sql) {
            $safeOperation = $this->sanitizer->sanitize($sql['operation'] ?? 'unknown');
            $safeSqlType = $this->sanitizer->sanitize($sql['type']);
            $safeSql = $this->sanitizer->sanitize($sql['sql']);
            $content .= "- **[{$safeOperation}]** ({$safeSqlType})\n";
            $content .= "  ```sql\n  {$safeSql}\n  ```\n";
        }

        return $content . "\n";
    }

    protected function formatForeignKeys(array $migration): string
    {
        if (empty($migration['foreign_keys'])) {
            return '';
        }

        $content = "**Foreign Keys:**\n";
        foreach ($migration['foreign_keys'] as $fk) {
            $safeColumn = $this->sanitizer->sanitize($fk['column']);
            $safeOnTable = $this->sanitizer->sanitize($fk['on_table'] ?? '');
            $safeReferences = $this->sanitizer->sanitize($fk['references'] ?? '');
            $ref = $fk['on_table'] ? "{$safeOnTable}.{$safeReferences}" : $safeReferences;
            $content .= "- `{$safeColumn}` → `{$ref}`\n";
        }

        return $content . "\n";
    }

    protected function formatIndexes(array $migration): string
    {
        if (empty($migration['indexes'])) {
            return '';
        }

        return "**Indexes:** " . count($migration['indexes']) . "\n\n";
    }

    protected function formatDependencies(array $migration): string
    {
        if (empty($migration['dependencies'])) {
            return '';
        }

        $content = "**Dependencies:**\n";
        foreach ($migration['dependencies'] as $type => $deps) {
            if (is_array($deps) && !empty($deps)) {
                $safeDepType = $this->sanitizer->sanitize($type);
                $content .= "- **{$safeDepType}:** " . count($deps) . "\n";
            }
        }

        return $content . "\n";
    }
}
