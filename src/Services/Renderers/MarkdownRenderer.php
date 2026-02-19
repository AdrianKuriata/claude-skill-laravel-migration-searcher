<?php

namespace DevSite\LaravelMigrationSearcher\Services\Renderers;

class MarkdownRenderer
{
    public function escapeHtml(string $value): string
    {
        return str_replace(['&', '<'], ['&amp;', '&lt;'], $value);
    }

    public function renderFullIndex(array $migrations): string
    {
        $content = "# Full Laravel Migrations Index\n\n";
        $content .= "**Generated:** " . now()->format('Y-m-d H:i:s') . "\n";
        $content .= "**Number of migrations:** " . count($migrations) . "\n\n";
        $content .= "---\n\n";

        $sorted = collect($migrations)->sortBy('timestamp')->values()->all();

        foreach ($sorted as $migration) {
            $content .= $this->formatMigrationFull($migration);
            $content .= "\n---\n\n";
        }

        return $content;
    }

    public function renderByTypeIndex(array $migrations): string
    {
        $content = "# Migrations Index - Grouped by Type\n\n";
        $content .= "**Generated:** " . now()->format('Y-m-d H:i:s') . "\n\n";

        $grouped = collect($migrations)->groupBy('type')->sortKeys();

        foreach ($grouped as $type => $typeMigrations) {
            $sorted = $typeMigrations->sortBy('timestamp')->values()->all();
            $safeType = $this->escapeHtml($type);

            $content .= "## {$safeType}\n\n";
            $content .= "**Count:** " . count($sorted) . "\n\n";

            foreach ($sorted as $migration) {
                $content .= $this->formatMigrationCompact($migration);
                $content .= "\n";
            }

            $content .= "\n---\n\n";
        }

        if ($grouped->isEmpty()) {
            $content .= "*No migrations found*\n\n";
        }

        return $content;
    }

    public function renderByTableIndex(array $migrations): string
    {
        $content = "# Migrations Index - Grouped by Tables\n\n";
        $content .= "**Generated:** " . now()->format('Y-m-d H:i:s') . "\n\n";

        $tablesMigrations = [];
        foreach ($migrations as $migration) {
            foreach ($migration['tables'] as $table => $tableInfo) {
                if (!isset($tablesMigrations[$table])) {
                    $tablesMigrations[$table] = [];
                }
                $tablesMigrations[$table][] = array_merge($migration, ['table_operation' => $tableInfo['operation']]);
            }
        }

        ksort($tablesMigrations);

        foreach ($tablesMigrations as $table => $tableMigrations) {
            $safeTable = $this->escapeHtml($table);
            $content .= "## Table: `{$safeTable}`\n\n";
            $content .= "**Number of migrations:** " . count($tableMigrations) . "\n\n";

            foreach ($tableMigrations as $migration) {
                $safeFilename = $this->escapeHtml($migration['filename']);
                $safeOp = $this->escapeHtml($migration['table_operation']);
                $safeType = $this->escapeHtml($migration['type']);
                $safePath = $this->escapeHtml($migration['relative_path']);
                $safeTimestamp = $this->escapeHtml($migration['timestamp']);

                $content .= "### [{$safeOp}] {$safeFilename}\n\n";
                $content .= "- **Migration type:** {$safeType}\n";
                $content .= "- **Path:** `{$safePath}`\n";
                $content .= "- **Timestamp:** {$safeTimestamp}\n";

                if (!empty($migration['columns'])) {
                    $safeColumns = array_map([$this, 'escapeHtml'], array_keys($migration['columns']));
                    $content .= "- **Columns:** " . implode(', ', $safeColumns) . "\n";
                }

                if (!empty($migration['ddl_operations'])) {
                    $content .= "- **DDL Operations:** " . count($migration['ddl_operations']) . "\n";
                }

                if (!empty($migration['dml_operations'])) {
                    $content .= "- **DML Operations:** " . $this->formatDMLSummary($migration['dml_operations']) . "\n";
                }

                $content .= "- **Complexity:** {$migration['complexity']}/10\n";
                $content .= "\n";
            }

            $content .= "---\n\n";
        }

        return $content;
    }

    public function renderByOperationIndex(array $migrations): string
    {
        $content = "# Migrations Index - Grouped by Operations\n\n";
        $content .= "**Generated:** " . now()->format('Y-m-d H:i:s') . "\n\n";

        $operations = [
            'CREATE' => 'Table Creation',
            'ALTER' => 'Structure Modifications',
            'DROP' => 'Table Deletion',
            'DATA' => 'Data Modifications',
            'RENAME' => 'Renaming',
        ];

        foreach ($operations as $op => $description) {
            $opMigrations = [];

            foreach ($migrations as $migration) {
                foreach ($migration['tables'] as $table => $tableInfo) {
                    if ($tableInfo['operation'] === $op) {
                        $opMigrations[] = array_merge($migration, [
                            'target_table' => $table,
                            'operation' => $op,
                        ]);
                    }
                }
            }

            $content .= "## {$description} ({$op})\n\n";
            $content .= "**Number of operations:** " . count($opMigrations) . "\n\n";

            if (count($opMigrations) > 0) {
                foreach ($opMigrations as $migration) {
                    $safeFilename = $this->escapeHtml($migration['filename']);
                    $safeTargetTable = $this->escapeHtml($migration['target_table']);
                    $safeType = $this->escapeHtml($migration['type']);
                    $safePath = $this->escapeHtml($migration['relative_path']);

                    $content .= "### {$safeFilename}\n\n";
                    $content .= "- **Table:** `{$safeTargetTable}`\n";
                    $content .= "- **Migration type:** {$safeType}\n";
                    $content .= "- **Path:** `{$safePath}`\n";

                    if ($op === 'ALTER' && !empty($migration['columns'])) {
                        $safeColumns = array_map([$this, 'escapeHtml'], array_keys($migration['columns']));
                        $content .= "- **Affected columns:** " . implode(', ', $safeColumns) . "\n";
                    }

                    if ($op === 'DATA' && !empty($migration['dml_operations'])) {
                        $content .= "- **DML Operations:**\n";
                        foreach ($migration['dml_operations'] as $dml) {
                            $safeDmlType = $this->escapeHtml($dml['type']);
                            $safeDmlTable = $this->escapeHtml($dml['table'] ?? $dml['model'] ?? 'unknown');
                            $content .= "  - **{$safeDmlType}** na `{$safeDmlTable}`";

                            if (!empty($dml['where_conditions'])) {
                                $safeConditions = array_map([$this, 'escapeHtml'], $dml['where_conditions']);
                                $content .= " WHERE: " . implode(' AND ', $safeConditions);
                            }
                            if (!empty($dml['columns_updated'])) {
                                $safeUpdated = array_map([$this, 'escapeHtml'], $dml['columns_updated']);
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

        $rawSqlMigrations = collect($migrations)
            ->filter(fn($m) => !empty($m['raw_sql']))
            ->values()
            ->all();

        $content .= "## Raw SQL\n\n";
        $content .= "**Number of migrations with raw SQL:** " . count($rawSqlMigrations) . "\n\n";

        foreach ($rawSqlMigrations as $migration) {
            $safeFilename = $this->escapeHtml($migration['filename']);
            $safeType = $this->escapeHtml($migration['type']);
            $safePath = $this->escapeHtml($migration['relative_path']);

            $content .= "### {$safeFilename}\n\n";
            $content .= "- **Migration type:** {$safeType}\n";
            $content .= "- **Path:** `{$safePath}`\n";
            $content .= "- **Number of statements:** " . count($migration['raw_sql']) . "\n\n";

            foreach ($migration['raw_sql'] as $sql) {
                $safeOperation = $this->escapeHtml($sql['operation'] ?? 'unknown');
                $safeSqlType = $this->escapeHtml($sql['type']);
                $safeSql = $this->escapeHtml($sql['sql']);
                $content .= "**[{$safeOperation}]** ({$safeSqlType}):\n";
                $content .= "```sql\n{$safeSql}\n```\n\n";
            }
        }

        return $content;
    }

    public function renderStats(array $migrations): string
    {
        $stats = [
            'generated_at' => now()->toIso8601String(),
            'total_migrations' => count($migrations),
            'by_type' => collect($migrations)->groupBy('type')->map->count()->sortKeys()->all(),
            'tables' => $this->getTableStats($migrations),
            'complexity' => [
                'average' => round(collect($migrations)->avg('complexity'), 2),
                'max' => collect($migrations)->max('complexity'),
                'high_complexity' => collect($migrations)->where('complexity', '>=', 7)->count(),
            ],
            'data_modifications' => collect($migrations)->where('has_data_modifications', true)->count(),
            'raw_sql_count' => collect($migrations)->filter(fn($m) => !empty($m['raw_sql']))->count(),
        ];

        return json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function formatMigrationFull(array $migration): string
    {
        $safeFilename = $this->escapeHtml($migration['filename']);
        $safeType = $this->escapeHtml($migration['type']);
        $safePath = $this->escapeHtml($migration['relative_path']);
        $safeTimestamp = $this->escapeHtml($migration['timestamp']);
        $safeName = $this->escapeHtml($migration['name']);

        $content = "### {$safeFilename}\n\n";
        $content .= "**Type:** {$safeType}  \n";
        $content .= "**Path:** `{$safePath}`  \n";
        $content .= "**Timestamp:** {$safeTimestamp}  \n";
        $content .= "**Name:** {$safeName}  \n";
        $content .= "**Complexity:** {$migration['complexity']}/10  \n\n";

        if (!empty($migration['tables'])) {
            $content .= "**Tables:**\n";
            foreach ($migration['tables'] as $table => $info) {
                $safeTable = $this->escapeHtml($table);
                $safeOp = $this->escapeHtml($info['operation']);
                $content .= "- `{$safeTable}` ({$safeOp})\n";
            }
            $content .= "\n";
        }

        if (!empty($migration['columns'])) {
            $content .= "**Columns:**\n";
            foreach ($migration['columns'] as $column => $info) {
                $safeColumn = $this->escapeHtml($column);
                $safeColType = $this->escapeHtml($info['type']);
                $modifiers = !empty($info['modifiers'])
                    ? ' [' . implode(', ', array_map([$this, 'escapeHtml'], $info['modifiers'])) . ']'
                    : '';
                $content .= "- `{$safeColumn}` ({$safeColType}{$modifiers})\n";
            }
            $content .= "\n";
        }

        if (!empty($migration['ddl_operations'])) {
            $content .= "**DDL Operations:**\n";
            $grouped = collect($migration['ddl_operations'])->groupBy('category');
            foreach ($grouped as $category => $ops) {
                $safeCategory = $this->escapeHtml($category);
                $content .= "- **{$safeCategory}:** " . count($ops) . " operations\n";
            }
            $content .= "\n";
        }

        if (!empty($migration['dml_operations'])) {
            $content .= "**DML Operations:**\n";
            foreach ($migration['dml_operations'] as $dml) {
                $safeDmlType = $this->escapeHtml($dml['type']);

                if (isset($dml['table'])) {
                    $safeDmlTable = $this->escapeHtml($dml['table']);
                    $content .= "- **{$safeDmlType}** na `{$safeDmlTable}`";

                    if (!empty($dml['where_conditions'])) {
                        $safeConditions = array_map([$this, 'escapeHtml'], $dml['where_conditions']);
                        $content .= "\n  - WHERE: " . implode(' AND ', $safeConditions);
                    }

                    if (!empty($dml['columns_updated'])) {
                        $safeUpdated = array_map([$this, 'escapeHtml'], $dml['columns_updated']);
                        $content .= "\n  - Columns: " . implode(', ', $safeUpdated);
                    }

                    if (!empty($dml['has_db_raw']) && !empty($dml['db_raw_expressions'])) {
                        $content .= "\n  - **⚠️ Uses DB::raw:**";
                        foreach ($dml['db_raw_expressions'] as $rawExpr) {
                            $preview = strlen($rawExpr) > 100 ? substr($rawExpr, 0, 100) . '...' : $rawExpr;
                            $safePreview = $this->escapeHtml($preview);
                            $content .= "\n    ```sql\n    {$safePreview}\n    ```";
                        }
                    }

                    if (!empty($dml['data_preview']) && empty($dml['has_db_raw'])) {
                        $safeDataPreview = $this->escapeHtml($dml['data_preview']);
                        $content .= "\n  - Data: " . $safeDataPreview;
                    }
                } elseif (isset($dml['model'])) {
                    $safeModel = $this->escapeHtml($dml['model']);
                    $safeMethod = $this->escapeHtml($dml['method'] ?? 'unknown');
                    $content .= "- **{$safeDmlType}** przez `{$safeModel}::{$safeMethod}`";

                    if (!empty($dml['note'])) {
                        $content .= "\n  - " . $this->escapeHtml($dml['note']);
                    }
                } elseif (isset($dml['variable'])) {
                    $safeVariable = $this->escapeHtml($dml['variable']);
                    $safeMethod = $this->escapeHtml($dml['method'] ?? 'unknown');
                    $content .= "- **{$safeDmlType}** przez `{$safeVariable}->{$safeMethod}`";

                    if (!empty($dml['relation'])) {
                        $content .= " (relation: " . $this->escapeHtml($dml['relation']) . ")";
                    }

                    if (!empty($dml['note'])) {
                        $content .= "\n  - " . $this->escapeHtml($dml['note']);
                    }
                } elseif ($dml['type'] === 'LOOP') {
                    $safeMethod = $this->escapeHtml($dml['method'] ?? 'unknown');
                    $content .= "- **🔁 LOOP** ({$safeMethod})";

                    if (!empty($dml['operations_in_loop'])) {
                        $safeOps = array_map([$this, 'escapeHtml'], $dml['operations_in_loop']);
                        $content .= "\n  - Operations: " . implode(', ', $safeOps);
                    }

                    if (!empty($dml['note'])) {
                        $content .= "\n  - " . $this->escapeHtml($dml['note']);
                    }
                }

                $content .= "\n";
            }
            $content .= "\n";
        }

        if (!empty($migration['raw_sql'])) {
            $content .= "**Raw SQL:** " . count($migration['raw_sql']) . " statement(s)\n\n";
            foreach ($migration['raw_sql'] as $sql) {
                $safeOperation = $this->escapeHtml($sql['operation'] ?? 'unknown');
                $safeSqlType = $this->escapeHtml($sql['type']);
                $safeSql = $this->escapeHtml($sql['sql']);
                $content .= "- **[{$safeOperation}]** ({$safeSqlType})\n";
                $content .= "  ```sql\n  {$safeSql}\n  ```\n";
            }
            $content .= "\n";
        }

        if (!empty($migration['foreign_keys'])) {
            $content .= "**Foreign Keys:**\n";
            foreach ($migration['foreign_keys'] as $fk) {
                $safeColumn = $this->escapeHtml($fk['column']);
                $safeOnTable = $this->escapeHtml($fk['on_table'] ?? '');
                $safeReferences = $this->escapeHtml($fk['references'] ?? '');
                $ref = $fk['on_table'] ? "{$safeOnTable}.{$safeReferences}" : $safeReferences;
                $content .= "- `{$safeColumn}` → `{$ref}`\n";
            }
            $content .= "\n";
        }

        if (!empty($migration['indexes'])) {
            $content .= "**Indexes:** " . count($migration['indexes']) . "\n\n";
        }

        if (!empty($migration['dependencies'])) {
            $content .= "**Dependencies:**\n";
            foreach ($migration['dependencies'] as $type => $deps) {
                if (is_array($deps) && !empty($deps)) {
                    $safeDepType = $this->escapeHtml($type);
                    $content .= "- **{$safeDepType}:** " . count($deps) . "\n";
                }
            }
            $content .= "\n";
        }

        return $content;
    }

    public function formatMigrationCompact(array $migration): string
    {
        $safeFilename = $this->escapeHtml($migration['filename']);
        $content = "### {$safeFilename}\n\n";

        $tables = !empty($migration['tables'])
            ? implode(', ', array_map([$this, 'escapeHtml'], array_keys($migration['tables'])))
            : 'none';
        $content .= "**Tables:** {$tables}  \n";

        if (!empty($migration['columns'])) {
            $safeColumns = array_map([$this, 'escapeHtml'], array_keys($migration['columns']));
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
        $summary = collect($dmlOperations)->groupBy('type')->map(fn($ops) => count($ops));
        $parts = [];
        foreach ($summary as $type => $count) {
            $parts[] = "{$type}: {$count}";
        }

        return implode(', ', $parts);
    }

    protected function getTableStats(array $migrations): array
    {
        $tables = [];

        foreach ($migrations as $migration) {
            foreach ($migration['tables'] as $table => $tableInfo) {
                if (!isset($tables[$table])) {
                    $tables[$table] = [
                        'migrations_count' => 0,
                        'operations' => [],
                    ];
                }
                $tables[$table]['migrations_count']++;
                $op = $tableInfo['operation'];
                $tables[$table]['operations'][$op] = ($tables[$table]['operations'][$op] ?? 0) + 1;
            }
        }

        uasort($tables, fn($a, $b) => $b['migrations_count'] <=> $a['migrations_count']);

        return array_slice($tables, 0, 50);
    }
}
