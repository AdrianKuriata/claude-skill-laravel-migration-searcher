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

    /** @param array<string, mixed> $data */
    public function renderFullIndex(array $data): string
    {
        /** @var string $title */
        $title = $data['title'] ?? '';
        /** @var string $generatedAt */
        $generatedAt = $data['generated_at'] ?? '';
        /** @var int $totalMigrations */
        $totalMigrations = $data['total_migrations'] ?? 0;
        /** @var list<MigrationArray> $migrations */
        $migrations = $data['migrations'] ?? [];

        $content = "# {$title}\n\n";
        $content .= "**Generated:** {$generatedAt}\n";
        $content .= "**Number of migrations:** {$totalMigrations}\n\n";
        $content .= "---\n\n";

        foreach ($migrations as $migration) {
            $content .= $this->formatter->formatMigrationFull($migration);
            $content .= "\n---\n\n";
        }

        return $content;
    }

    /** @param array<string, mixed> $data */
    public function renderByTypeIndex(array $data): string
    {
        /** @var string $title */
        $title = $data['title'] ?? '';
        /** @var string $generatedAt */
        $generatedAt = $data['generated_at'] ?? '';
        /** @var array<string, array{count: int, migrations: list<MigrationArray>}> $groups */
        $groups = $data['groups'] ?? [];

        $content = "# {$title}\n\n";
        $content .= "**Generated:** {$generatedAt}\n\n";

        if (empty($groups)) {
            $content .= "*No migrations found*\n\n";
            return $content;
        }

        foreach ($groups as $type => $group) {
            $safeType = $this->sanitizer->sanitize((string) $type);

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

    /** @param array<string, mixed> $data */
    public function renderByTableIndex(array $data): string
    {
        /** @var string $title */
        $title = $data['title'] ?? '';
        /** @var string $generatedAt */
        $generatedAt = $data['generated_at'] ?? '';
        /** @var array<string, array{count: int, migrations: list<MigrationWithTableOp>}> $tables */
        $tables = $data['tables'] ?? [];

        $content = "# {$title}\n\n";
        $content .= "**Generated:** {$generatedAt}\n\n";

        foreach ($tables as $table => $tableData) {
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

                $columns = $migration['columns'];
                if (!empty($columns)) {
                    $safeColumns = array_map([$this->sanitizer, 'sanitize'], array_keys($columns));
                    $content .= "- **Columns:** " . implode(', ', $safeColumns) . "\n";
                }

                $ddlOperations = $migration['ddl_operations'];
                if (!empty($ddlOperations)) {
                    $content .= "- **DDL Operations:** " . count($ddlOperations) . "\n";
                }

                $dmlOperations = $migration['dml_operations'];
                if (!empty($dmlOperations)) {
                    $content .= "- **DML Operations:** " . $this->formatter->formatDMLSummary($dmlOperations) . "\n";
                }

                $complexity = $migration['complexity'];
                $content .= "- **Complexity:** {$complexity}/10\n";
                $content .= "\n";
            }

            $content .= "---\n\n";
        }

        return $content;
    }

    /** @param array<string, mixed> $data */
    public function renderByOperationIndex(array $data): string
    {
        /** @var string $title */
        $title = $data['title'] ?? '';
        /** @var string $generatedAt */
        $generatedAt = $data['generated_at'] ?? '';
        /** @var array<string, array{description: string, count: int, migrations: list<MigrationWithTargetOp>}> $operations */
        $operations = $data['operations'] ?? [];

        $content = "# {$title}\n\n";
        $content .= "**Generated:** {$generatedAt}\n\n";

        foreach ($operations as $op => $opData) {
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

                    $columns = $migration['columns'];
                    if ($op === 'ALTER' && !empty($columns)) {
                        $safeColumns = array_map([$this->sanitizer, 'sanitize'], array_keys($columns));
                        $content .= "- **Affected columns:** " . implode(', ', $safeColumns) . "\n";
                    }

                    $dmlOperations = $migration['dml_operations'];
                    if ($op === 'DATA' && !empty($dmlOperations)) {
                        $content .= "- **DML Operations:**\n";
                        foreach ($dmlOperations as $dml) {
                            $safeDmlType = $this->sanitizer->sanitize($dml['type']);
                            $safeDmlTable = $this->sanitizer->sanitize($dml['table'] ?? $dml['model'] ?? 'unknown');
                            $content .= "  - **{$safeDmlType}** on `{$safeDmlTable}`";

                            $whereConditions = $dml['where_conditions'];
                            if (!empty($whereConditions)) {
                                $safeConditions = array_map([$this->sanitizer, 'sanitize'], $whereConditions);
                                $content .= " WHERE: " . implode(' AND ', $safeConditions);
                            }
                            $columnsUpdated = $dml['columns_updated'];
                            if (!empty($columnsUpdated)) {
                                $safeUpdated = array_map([$this->sanitizer, 'sanitize'], $columnsUpdated);
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

        /** @var array{count: int, migrations: list<MigrationArray>} $rawSqlData */
        $rawSqlData = $data['raw_sql'] ?? ['count' => 0, 'migrations' => []];

        $content .= "## Raw SQL\n\n";
        $content .= "**Number of migrations with raw SQL:** {$rawSqlData['count']}\n\n";

        foreach ($rawSqlData['migrations'] as $migration) {
            $safeFilename = $this->sanitizer->sanitize($migration['filename']);
            $safeType = $this->sanitizer->sanitize($migration['type']);
            $safePath = $this->sanitizer->sanitize($migration['relative_path']);

            $content .= "### {$safeFilename}\n\n";
            $content .= "- **Migration type:** {$safeType}\n";
            $content .= "- **Path:** `{$safePath}`\n";
            $content .= "- **Number of statements:** " . count($migration['raw_sql']) . "\n\n";

            foreach ($migration['raw_sql'] as $sql) {
                $safeOperation = $this->sanitizer->sanitize($sql['operation']);
                $safeSqlType = $this->sanitizer->sanitize($sql['type']);
                $safeSql = $this->sanitizer->sanitize($sql['sql']);
                $content .= "**[{$safeOperation}]** ({$safeSqlType}):\n";
                $content .= "```sql\n{$safeSql}\n```\n\n";
            }
        }

        return $content;
    }

    /** @param array<string, mixed> $data */
    public function renderStats(array $data): string
    {
        /** @var string $generatedAt */
        $generatedAt = $data['generated_at'] ?? '';
        /** @var int $totalMigrations */
        $totalMigrations = $data['total_migrations'] ?? 0;
        /** @var array<string, int> $byType */
        $byType = $data['by_type'] ?? [];
        /** @var array{average: float|int, max: int, high_complexity: int} $complexity */
        $complexity = $data['complexity'] ?? ['average' => 0, 'max' => 0, 'high_complexity' => 0];
        /** @var int $dataModifications */
        $dataModifications = $data['data_modifications'] ?? 0;
        /** @var int $rawSqlCount */
        $rawSqlCount = $data['raw_sql_count'] ?? 0;
        /** @var array<string, array{migrations_count: int, operations: array<string, int>}> $tables */
        $tables = $data['tables'] ?? [];

        $content = "# Migration Statistics\n\n";
        $content .= "**Generated:** {$generatedAt}\n";
        $content .= "**Total migrations:** {$totalMigrations}\n\n";

        $content .= "## By Type\n\n";
        if (!empty($byType)) {
            foreach ($byType as $type => $count) {
                $safeType = $this->sanitizer->sanitize((string) $type);
                $content .= "- **{$safeType}:** {$count}\n";
            }
        }
        $content .= "\n";

        $content .= "## Complexity\n\n";
        $content .= "- **Average:** {$complexity['average']}\n";
        $content .= "- **Max:** {$complexity['max']}\n";
        $content .= "- **High complexity (>=7):** {$complexity['high_complexity']}\n\n";

        $content .= "## Data\n\n";
        $content .= "- **Data modifications:** {$dataModifications}\n";
        $content .= "- **Raw SQL migrations:** {$rawSqlCount}\n\n";

        if (!empty($tables)) {
            $content .= "## Tables (top " . count($tables) . ")\n\n";
            foreach ($tables as $table => $info) {
                $ops = implode(', ', array_map(
                    fn (string $opKey, int $opCount): string => "{$this->sanitizer->sanitize($opKey)}: {$opCount}",
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
