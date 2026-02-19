<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use Illuminate\Support\Facades\File;

class IndexGenerator
{
    protected array $migrations = [];
    protected string $outputPath;

    public function __construct(string $outputPath)
    {
        $this->outputPath = rtrim($outputPath, '/');
    }
    
    public function setMigrations(array $migrations): void
    {
        $this->migrations = $migrations;
    }
    
    public function generateAll(): array
    {
        $generated = [];

        // Ensure directory exists
        if (!File::exists($this->outputPath)) {
            File::makeDirectory($this->outputPath, 0755, true);
        }

        $generated['full'] = $this->generateFullIndex();
        $generated['by_type'] = $this->generateByTypeIndex();
        $generated['by_table'] = $this->generateByTableIndex();
        $generated['by_operation'] = $this->generateByOperationIndex();
        $generated['stats'] = $this->generateStats();

        return $generated;
    }
    
    protected function generateFullIndex(): string
    {
        $filepath = $this->outputPath . '/index-full.md';
        
        $content = "# Full Laravel Migrations Index\n\n";
        $content .= "**Generated:** " . now()->format('Y-m-d H:i:s') . "\n";
        $content .= "**Number of migrations:** " . count($this->migrations) . "\n\n";
        $content .= "---\n\n";

        // Sort chronologically
        $sorted = collect($this->migrations)->sortBy('timestamp')->values()->all();

        foreach ($sorted as $migration) {
            $content .= $this->formatMigrationFull($migration);
            $content .= "\n---\n\n";
        }

        File::put($filepath, $content);
        return $filepath;
    }
    
    protected function generateByTypeIndex(): string
    {
        $filepath = $this->outputPath . '/index-by-type.md';
        
        $content = "# Migrations Index - Grouped by Type\n\n";
        $content .= "**Generated:** " . now()->format('Y-m-d H:i:s') . "\n\n";

        $types = [
            'system' => 'System Migrations (database/migrations)',
            'instances' => 'Instance Migrations (database/instances/migrations)',
            'before' => 'BEFORE Import Migrations (app/Console/Commands/ImportInstance/migrations/before)',
            'after' => 'AFTER Import Migrations (app/Console/Commands/ImportInstance/migrations/after)'
        ];

        foreach ($types as $type => $description) {
            $typeMigrations = collect($this->migrations)
                ->filter(fn($m) => $m['type'] === $type)
                ->sortBy('timestamp')
                ->values()
                ->all();

            $content .= "## {$description}\n\n";
            $content .= "**Count:** " . count($typeMigrations) . "\n\n";

            if (count($typeMigrations) > 0) {
                foreach ($typeMigrations as $migration) {
                    $content .= $this->formatMigrationCompact($migration);
                    $content .= "\n";
                }
            } else {
                $content .= "*No migrations of this type*\n";
            }

            $content .= "\n---\n\n";
        }

        File::put($filepath, $content);
        return $filepath;
    }
    
    protected function generateByTableIndex(): string
    {
        $filepath = $this->outputPath . '/index-by-table.md';
        
        $content = "# Migrations Index - Grouped by Tables\n\n";
        $content .= "**Generated:** " . now()->format('Y-m-d H:i:s') . "\n\n";

        // Collect all tables
        $tablesMigrations = [];
        foreach ($this->migrations as $migration) {
            foreach ($migration['tables'] as $table => $tableInfo) {
                if (!isset($tablesMigrations[$table])) {
                    $tablesMigrations[$table] = [];
                }
                $tablesMigrations[$table][] = array_merge($migration, ['table_operation' => $tableInfo['operation']]);
            }
        }

        // Sort alphabetically by table names
        ksort($tablesMigrations);

        foreach ($tablesMigrations as $table => $migrations) {
            $content .= "## Table: `{$table}`\n\n";
            $content .= "**Number of migrations:** " . count($migrations) . "\n\n";

            foreach ($migrations as $migration) {
                $content .= "### [{$migration['table_operation']}] {$migration['filename']}\n\n";
                $content .= "- **Migration type:** {$migration['type']}\n";
                $content .= "- **Path:** `{$migration['relative_path']}`\n";
                $content .= "- **Timestamp:** {$migration['timestamp']}\n";
                
                if (!empty($migration['columns'])) {
                    $content .= "- **Columns:** " . implode(', ', array_keys($migration['columns'])) . "\n";
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

        File::put($filepath, $content);
        return $filepath;
    }
    
    protected function generateByOperationIndex(): string
    {
        $filepath = $this->outputPath . '/index-by-operation.md';
        
        $content = "# Migrations Index - Grouped by Operations\n\n";
        $content .= "**Generated:** " . now()->format('Y-m-d H:i:s') . "\n\n";

        $operations = [
            'CREATE' => 'Table Creation',
            'ALTER' => 'Structure Modifications',
            'DROP' => 'Table Deletion',
            'DATA' => 'Data Modifications',
            'RENAME' => 'Renaming'
        ];

        foreach ($operations as $op => $description) {
            $opMigrations = [];
            
            foreach ($this->migrations as $migration) {
                foreach ($migration['tables'] as $table => $tableInfo) {
                    if ($tableInfo['operation'] === $op) {
                        $opMigrations[] = array_merge($migration, [
                            'target_table' => $table,
                            'operation' => $op
                        ]);
                    }
                }
            }

            $content .= "## {$description} ({$op})\n\n";
            $content .= "**Number of operations:** " . count($opMigrations) . "\n\n";

            if (count($opMigrations) > 0) {
                foreach ($opMigrations as $migration) {
                    $content .= "### {$migration['filename']}\n\n";
                    $content .= "- **Table:** `{$migration['target_table']}`\n";
                    $content .= "- **Migration type:** {$migration['type']}\n";
                    $content .= "- **Path:** `{$migration['relative_path']}`\n";
                    
                    if ($op === 'ALTER' && !empty($migration['columns'])) {
                        $content .= "- **Affected columns:** " . implode(', ', array_keys($migration['columns'])) . "\n";
                    }

                    if ($op === 'DATA' && !empty($migration['dml_operations'])) {
                        $content .= "- **DML Operations:**\n";
                        foreach ($migration['dml_operations'] as $dml) {
                            $type = $dml['type'];
                            $table = $dml['table'] ?? $dml['model'] ?? 'unknown';
                            $content .= "  - **{$type}** na `{$table}`";
                            
                            if (!empty($dml['where_conditions'])) {
                                $content .= " WHERE: " . implode(' AND ', $dml['where_conditions']);
                            }
                            if (!empty($dml['columns_updated'])) {
                                $content .= " (columns: " . implode(', ', $dml['columns_updated']) . ")";
                            }
                            $content .= "\n";
                        }
                    }

                    $content .= "\n";
                }
            }

            $content .= "---\n\n";
        }

        // Special section: Migrations with Raw SQL
        $rawSqlMigrations = collect($this->migrations)
            ->filter(fn($m) => !empty($m['raw_sql']))
            ->values()
            ->all();

        $content .= "## Raw SQL\n\n";
        $content .= "**Number of migrations with raw SQL:** " . count($rawSqlMigrations) . "\n\n";

        foreach ($rawSqlMigrations as $migration) {
            $content .= "### {$migration['filename']}\n\n";
            $content .= "- **Migration type:** {$migration['type']}\n";
            $content .= "- **Path:** `{$migration['relative_path']}`\n";
            $content .= "- **Number of statements:** " . count($migration['raw_sql']) . "\n\n";
            
            foreach ($migration['raw_sql'] as $sql) {
                $operation = $sql['operation'] ?? 'unknown';
                $content .= "**[{$operation}]** ({$sql['type']}):\n";
                $content .= "```sql\n{$sql['sql']}\n```\n\n";
            }
        }

        File::put($filepath, $content);
        return $filepath;
    }
    
    protected function generateStats(): string
    {
        $filepath = $this->outputPath . '/stats.json';

        $stats = [
            'generated_at' => now()->toIso8601String(),
            'total_migrations' => count($this->migrations),
            'by_type' => [
                'system' => collect($this->migrations)->where('type', 'system')->count(),
                'instances' => collect($this->migrations)->where('type', 'instances')->count(),
                'before' => collect($this->migrations)->where('type', 'before')->count(),
                'after' => collect($this->migrations)->where('type', 'after')->count(),
            ],
            'tables' => $this->getTableStats(),
            'complexity' => [
                'average' => round(collect($this->migrations)->avg('complexity'), 2),
                'max' => collect($this->migrations)->max('complexity'),
                'high_complexity' => collect($this->migrations)->where('complexity', '>=', 7)->count(),
            ],
            'data_modifications' => collect($this->migrations)->where('has_data_modifications', true)->count(),
            'raw_sql_count' => collect($this->migrations)->filter(fn($m) => !empty($m['raw_sql']))->count(),
        ];

        File::put($filepath, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $filepath;
    }
    
    protected function getTableStats(): array
    {
        $tables = [];
        
        foreach ($this->migrations as $migration) {
            foreach ($migration['tables'] as $table => $tableInfo) {
                if (!isset($tables[$table])) {
                    $tables[$table] = [
                        'migrations_count' => 0,
                        'operations' => []
                    ];
                }
                $tables[$table]['migrations_count']++;
                $op = $tableInfo['operation'];
                $tables[$table]['operations'][$op] = ($tables[$table]['operations'][$op] ?? 0) + 1;
            }
        }

        // Sort by migration count (descending)
        uasort($tables, fn($a, $b) => $b['migrations_count'] <=> $a['migrations_count']);

        return array_slice($tables, 0, 50); // Top 50 tables
    }
    
    protected function formatMigrationFull(array $migration): string
    {
        $content = "### {$migration['filename']}\n\n";
        $content .= "**Type:** {$migration['type']}  \n";
        $content .= "**Path:** `{$migration['relative_path']}`  \n";
        $content .= "**Timestamp:** {$migration['timestamp']}  \n";
        $content .= "**Name:** {$migration['name']}  \n";
        $content .= "**Complexity:** {$migration['complexity']}/10  \n\n";

        // Tables
        if (!empty($migration['tables'])) {
            $content .= "**Tables:**\n";
            foreach ($migration['tables'] as $table => $info) {
                $content .= "- `{$table}` ({$info['operation']})\n";
            }
            $content .= "\n";
        }

        // Columns
        if (!empty($migration['columns'])) {
            $content .= "**Columns:**\n";
            foreach ($migration['columns'] as $column => $info) {
                $modifiers = !empty($info['modifiers']) ? ' [' . implode(', ', $info['modifiers']) . ']' : '';
                $content .= "- `{$column}` ({$info['type']}{$modifiers})\n";
            }
            $content .= "\n";
        }

        // DDL Operations
        if (!empty($migration['ddl_operations'])) {
            $content .= "**DDL Operations:**\n";
            $grouped = collect($migration['ddl_operations'])->groupBy('category');
            foreach ($grouped as $category => $ops) {
                $content .= "- **{$category}:** " . count($ops) . " operations\n";
            }
            $content .= "\n";
        }

        // DML Operations
        if (!empty($migration['dml_operations'])) {
            $content .= "**DML Operations:**\n";
            foreach ($migration['dml_operations'] as $dml) {
                $type = $dml['type'];
                
                // Table operations (DB::table)
                if (isset($dml['table'])) {
                    $table = $dml['table'];
                    $content .= "- **{$type}** na `{$table}`";
                    
                    // WHERE conditions
                    if (!empty($dml['where_conditions'])) {
                        $content .= "\n  - WHERE: " . implode(' AND ', $dml['where_conditions']);
                    }
                    
                    // Columns being updated
                    if (!empty($dml['columns_updated'])) {
                        $content .= "\n  - Columns: " . implode(', ', $dml['columns_updated']);
                    }
                    
                    // DB::raw expressions
                    if (!empty($dml['has_db_raw']) && !empty($dml['db_raw_expressions'])) {
                        $content .= "\n  - **⚠️ Uses DB::raw:**";
                        foreach ($dml['db_raw_expressions'] as $rawExpr) {
                            $preview = strlen($rawExpr) > 100 ? substr($rawExpr, 0, 100) . '...' : $rawExpr;
                            $content .= "\n    ```sql\n    {$preview}\n    ```";
                        }
                    }
                    
                    // Data preview
                    if (!empty($dml['data_preview']) && empty($dml['has_db_raw'])) {
                        $content .= "\n  - Data: " . $dml['data_preview'];
                    }
                }
                // Operations through Eloquent (Model::create, ->save(), etc.)
                elseif (isset($dml['model'])) {
                    $model = $dml['model'];
                    $method = $dml['method'] ?? 'unknown';
                    $content .= "- **{$type}** przez `{$model}::{$method}`";
                    
                    if (!empty($dml['note'])) {
                        $content .= "\n  - {$dml['note']}";
                    }
                }
                // Operations through variables (->save(), ->delete())
                elseif (isset($dml['variable'])) {
                    $variable = $dml['variable'];
                    $method = $dml['method'] ?? 'unknown';
                    $content .= "- **{$type}** przez `{$variable}->{$method}`";
                    
                    if (!empty($dml['relation'])) {
                        $content .= " (relation: {$dml['relation']})";
                    }
                    
                    if (!empty($dml['note'])) {
                        $content .= "\n  - {$dml['note']}";
                    }
                }
                // Loop operations
                elseif ($type === 'LOOP') {
                    $method = $dml['method'] ?? 'unknown';
                    $content .= "- **🔁 LOOP** ({$method})";

                    if (!empty($dml['operations_in_loop'])) {
                        $content .= "\n  - Operations: " . implode(', ', $dml['operations_in_loop']);
                    }
                    
                    if (!empty($dml['note'])) {
                        $content .= "\n  - {$dml['note']}";
                    }
                }
                
                $content .= "\n";
            }
            $content .= "\n";
        }

        // Raw SQL
        if (!empty($migration['raw_sql'])) {
            $content .= "**Raw SQL:** " . count($migration['raw_sql']) . " statement(s)\n\n";
            foreach ($migration['raw_sql'] as $sql) {
                $operation = $sql['operation'] ?? 'unknown';
                $content .= "- **[{$operation}]** ({$sql['type']})\n";
                $content .= "  ```sql\n  {$sql['sql']}\n  ```\n";
            }
            $content .= "\n";
        }

        // Foreign Keys
        if (!empty($migration['foreign_keys'])) {
            $content .= "**Foreign Keys:**\n";
            foreach ($migration['foreign_keys'] as $fk) {
                $ref = $fk['on_table'] ? "{$fk['on_table']}.{$fk['references']}" : $fk['references'];
                $content .= "- `{$fk['column']}` → `{$ref}`\n";
            }
            $content .= "\n";
        }

        // Indexes
        if (!empty($migration['indexes'])) {
            $content .= "**Indexes:** " . count($migration['indexes']) . "\n\n";
        }

        // Dependencies
        if (!empty($migration['dependencies'])) {
            $content .= "**Dependencies:**\n";
            foreach ($migration['dependencies'] as $type => $deps) {
                if (is_array($deps) && !empty($deps)) {
                    $content .= "- **{$type}:** " . count($deps) . "\n";
                }
            }
            $content .= "\n";
        }

        return $content;
    }
    
    protected function formatMigrationCompact(array $migration): string
    {
        $content = "### {$migration['filename']}\n\n";
        
        $tables = !empty($migration['tables']) ? implode(', ', array_keys($migration['tables'])) : 'none';
        $content .= "**Tables:** {$tables}  \n";

        if (!empty($migration['columns'])) {
            $content .= "**Columns:** " . implode(', ', array_keys($migration['columns'])) . "  \n";
        }

        if ($migration['has_data_modifications']) {
            $content .= "**⚠️ Modifies data**  \n";
        }

        $content .= "**Complexity:** {$migration['complexity']}/10  \n";

        return $content;
    }
    
    protected function formatDMLSummary(array $dmlOperations): string
    {
        $summary = collect($dmlOperations)->groupBy('type')->map(fn($ops) => count($ops));
        $parts = [];
        foreach ($summary as $type => $count) {
            $parts[] = "{$type}: {$count}";
        }
        return implode(', ', $parts);
    }
}
