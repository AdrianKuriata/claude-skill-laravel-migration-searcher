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

    /** @param MigrationArray $migration */
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

    /** @param MigrationArray $migration */
    public function formatMigrationCompact(array $migration): string
    {
        $safeFilename = $this->sanitizer->sanitize($migration['filename']);
        $content = "### {$safeFilename}\n\n";

        $tables = $migration['tables'];
        $tablesStr = !empty($tables)
            ? implode(', ', array_map([$this->sanitizer, 'sanitize'], array_keys($tables)))
            : 'none';
        $content .= "**Tables:** {$tablesStr}  \n";

        $columns = $migration['columns'];
        if (!empty($columns)) {
            $safeColumns = array_map([$this->sanitizer, 'sanitize'], array_keys($columns));
            $content .= "**Columns:** " . implode(', ', $safeColumns) . "  \n";
        }

        if ($migration['has_data_modifications']) {
            $content .= "**Warning: Modifies data**  \n";
        }

        $complexity = $migration['complexity'];
        $content .= "**Complexity:** {$complexity}/10  \n";

        return $content;
    }

    /** @param list<DmlOperationArray> $dmlOperations */
    public function formatDMLSummary(array $dmlOperations): string
    {
        $summary = collect($dmlOperations)->groupBy('type')->map(fn ($ops): int => count($ops));
        $parts = [];
        foreach ($summary as $type => $count) {
            $parts[] = "{$type}: {$count}";
        }

        return implode(', ', $parts);
    }

    /** @param MigrationArray $migration */
    protected function formatHeader(array $migration): string
    {
        $safeFilename = $this->sanitizer->sanitize($migration['filename']);
        $safeType = $this->sanitizer->sanitize($migration['type']);
        $safePath = $this->sanitizer->sanitize($migration['relative_path']);
        $safeTimestamp = $this->sanitizer->sanitize($migration['timestamp']);
        $safeName = $this->sanitizer->sanitize($migration['name']);
        $complexity = $migration['complexity'];

        $content = "### {$safeFilename}\n\n";
        $content .= "**Type:** {$safeType}  \n";
        $content .= "**Path:** `{$safePath}`  \n";
        $content .= "**Timestamp:** {$safeTimestamp}  \n";
        $content .= "**Name:** {$safeName}  \n";
        $content .= "**Complexity:** {$complexity}/10  \n\n";

        return $content;
    }

    /** @param MigrationArray $migration */
    protected function formatTables(array $migration): string
    {
        $tables = $migration['tables'];
        if (empty($tables)) {
            return '';
        }

        $content = "**Tables:**\n";
        foreach ($tables as $table => $info) {
            $safeTable = $this->sanitizer->sanitize($table);
            $safeOp = $this->sanitizer->sanitize($info['operation']);
            $content .= "- `{$safeTable}` ({$safeOp})\n";
        }

        return $content . "\n";
    }

    /** @param MigrationArray $migration */
    protected function formatColumns(array $migration): string
    {
        $columns = $migration['columns'];
        if (empty($columns)) {
            return '';
        }

        $content = "**Columns:**\n";
        foreach ($columns as $column => $info) {
            $safeColumn = $this->sanitizer->sanitize($column);
            $safeColType = $this->sanitizer->sanitize($info['type']);
            $modifiers = $info['modifiers'];
            $modifierStr = !empty($modifiers)
                ? ' [' . implode(', ', array_map([$this->sanitizer, 'sanitize'], $modifiers)) . ']'
                : '';
            $content .= "- `{$safeColumn}` ({$safeColType}{$modifierStr})\n";
        }

        return $content . "\n";
    }

    /** @param MigrationArray $migration */
    protected function formatDdlOperations(array $migration): string
    {
        $ddlOperations = $migration['ddl_operations'];
        if (empty($ddlOperations)) {
            return '';
        }

        $content = "**DDL Operations:**\n";
        $grouped = collect($ddlOperations)->groupBy('category');
        foreach ($grouped as $category => $ops) {
            $safeCategory = $this->sanitizer->sanitize((string) $category);
            $content .= "- **{$safeCategory}:** " . count($ops) . " operations\n";
        }

        return $content . "\n";
    }

    /** @param MigrationArray $migration */
    protected function formatDmlOperations(array $migration): string
    {
        $dmlOperations = $migration['dml_operations'];
        if (empty($dmlOperations)) {
            return '';
        }

        $content = "**DML Operations:**\n";
        foreach ($dmlOperations as $dml) {
            $content .= $this->formatSingleDmlOperation($dml);
        }

        return $content . "\n";
    }

    /** @param DmlOperationArray $dml */
    protected function formatSingleDmlOperation(array $dml): string
    {
        $safeDmlType = $this->sanitizer->sanitize($dml['type']);
        $content = '';

        if ($dml['table'] !== null) {
            $content .= $this->formatTableDmlOperation($dml, $safeDmlType);
        } elseif ($dml['model'] !== null) {
            $content .= $this->formatModelDmlOperation($dml, $safeDmlType);
        } elseif ($dml['variable'] !== null) {
            $content .= $this->formatVariableDmlOperation($dml, $safeDmlType);
        } elseif ($dml['type'] === 'LOOP') {
            $content .= $this->formatLoopDmlOperation($dml);
        }

        $content .= "\n";

        return $content;
    }

    /** @param DmlOperationArray $dml */
    protected function formatTableDmlOperation(array $dml, string $safeDmlType): string
    {
        $safeDmlTable = $this->sanitizer->sanitize($dml['table'] ?? '');
        $content = "- **{$safeDmlType}** on `{$safeDmlTable}`";

        $whereConditions = $dml['where_conditions'];
        if (!empty($whereConditions)) {
            $safeConditions = array_map([$this->sanitizer, 'sanitize'], $whereConditions);
            $content .= "\n  - WHERE: " . implode(' AND ', $safeConditions);
        }

        $columnsUpdated = $dml['columns_updated'];
        if (!empty($columnsUpdated)) {
            $safeUpdated = array_map([$this->sanitizer, 'sanitize'], $columnsUpdated);
            $content .= "\n  - Columns: " . implode(', ', $safeUpdated);
        }

        $dbRawExpressions = $dml['db_raw_expressions'];
        if ($dml['has_db_raw'] && !empty($dbRawExpressions)) {
            $content .= "\n  - **Warning: Uses DB::raw:**";
            foreach ($dbRawExpressions as $rawExpr) {
                $preview = strlen($rawExpr) > 100 ? substr($rawExpr, 0, 100) . '...' : $rawExpr;
                $safePreview = $this->sanitizer->sanitize($preview);
                $content .= "\n    ```sql\n    {$safePreview}\n    ```";
            }
        }

        if ($dml['data_preview'] !== null && !$dml['has_db_raw']) {
            $safeDataPreview = $this->sanitizer->sanitize($dml['data_preview']);
            $content .= "\n  - Data: " . $safeDataPreview;
        }

        return $content;
    }

    /** @param DmlOperationArray $dml */
    protected function formatModelDmlOperation(array $dml, string $safeDmlType): string
    {
        $safeModel = $this->sanitizer->sanitize($dml['model'] ?? '');
        $safeMethod = $this->sanitizer->sanitize($dml['method'] ?? 'unknown');
        $content = "- **{$safeDmlType}** via `{$safeModel}::{$safeMethod}`";

        if ($dml['note'] !== null) {
            $content .= "\n  - " . $this->sanitizer->sanitize($dml['note']);
        }

        return $content;
    }

    /** @param DmlOperationArray $dml */
    protected function formatVariableDmlOperation(array $dml, string $safeDmlType): string
    {
        $safeVariable = $this->sanitizer->sanitize($dml['variable'] ?? '');
        $safeMethod = $this->sanitizer->sanitize($dml['method'] ?? 'unknown');
        $content = "- **{$safeDmlType}** via `{$safeVariable}->{$safeMethod}`";

        if ($dml['relation'] !== null) {
            $content .= " (relation: " . $this->sanitizer->sanitize($dml['relation']) . ")";
        }

        if ($dml['note'] !== null) {
            $content .= "\n  - " . $this->sanitizer->sanitize($dml['note']);
        }

        return $content;
    }

    /** @param DmlOperationArray $dml */
    protected function formatLoopDmlOperation(array $dml): string
    {
        $safeMethod = $this->sanitizer->sanitize($dml['method'] ?? 'unknown');
        $content = "- **LOOP** ({$safeMethod})";

        $operationsInLoop = $dml['operations_in_loop'];
        if (!empty($operationsInLoop)) {
            $safeOps = array_map([$this->sanitizer, 'sanitize'], $operationsInLoop);
            $content .= "\n  - Operations: " . implode(', ', $safeOps);
        }

        if ($dml['note'] !== null) {
            $content .= "\n  - " . $this->sanitizer->sanitize($dml['note']);
        }

        return $content;
    }

    /** @param MigrationArray $migration */
    protected function formatRawSql(array $migration): string
    {
        $rawSql = $migration['raw_sql'];
        if (empty($rawSql)) {
            return '';
        }

        $content = "**Raw SQL:** " . count($rawSql) . " statement(s)\n\n";
        foreach ($rawSql as $sql) {
            $safeOperation = $this->sanitizer->sanitize($sql['operation']);
            $safeSqlType = $this->sanitizer->sanitize($sql['type']);
            $safeSql = $this->sanitizer->sanitize($sql['sql']);
            $content .= "- **[{$safeOperation}]** ({$safeSqlType})\n";
            $content .= "  ```sql\n  {$safeSql}\n  ```\n";
        }

        return $content . "\n";
    }

    /** @param MigrationArray $migration */
    protected function formatForeignKeys(array $migration): string
    {
        $foreignKeys = $migration['foreign_keys'];
        if (empty($foreignKeys)) {
            return '';
        }

        $content = "**Foreign Keys:**\n";
        foreach ($foreignKeys as $fk) {
            $safeColumn = $this->sanitizer->sanitize($fk['column']);
            $safeOnTable = $this->sanitizer->sanitize($fk['on_table'] ?? '');
            $safeReferences = $this->sanitizer->sanitize($fk['references'] ?? '');
            $ref = $fk['on_table'] !== null ? "{$safeOnTable}.{$safeReferences}" : $safeReferences;
            $content .= "- `{$safeColumn}` → `{$ref}`\n";
        }

        return $content . "\n";
    }

    /** @param MigrationArray $migration */
    protected function formatIndexes(array $migration): string
    {
        $indexes = $migration['indexes'];
        if (empty($indexes)) {
            return '';
        }

        return "**Indexes:** " . count($indexes) . "\n\n";
    }

    /** @param MigrationArray $migration */
    protected function formatDependencies(array $migration): string
    {
        $dependencies = $migration['dependencies'];

        $hasRequires = !empty($dependencies['requires']);
        $hasDependsOn = !empty($dependencies['depends_on']);
        $hasForeignKeys = !empty($dependencies['foreign_keys']);

        if (!$hasRequires && !$hasDependsOn && !$hasForeignKeys) {
            return '';
        }

        $content = "**Dependencies:**\n";
        if ($hasRequires) {
            $content .= "- **requires:** " . count($dependencies['requires']) . "\n";
        }
        if ($hasDependsOn) {
            $content .= "- **depends_on:** " . count($dependencies['depends_on']) . "\n";
        }
        if ($hasForeignKeys) {
            $content .= "- **foreign_keys:** " . count($dependencies['foreign_keys']) . "\n";
        }

        return $content . "\n";
    }
}
