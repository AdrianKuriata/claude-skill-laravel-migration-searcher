<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MigrationAnalyzer
{
    protected array $result = [];
    protected string $content = '';
    protected string $filename = '';
    protected string $filepath = '';
    protected string $type = '';
    
    public function analyze(string $filepath, string $type): array
    {
        $this->filepath = $filepath;
        $this->filename = basename($filepath);
        $this->type = $type;
        $this->content = File::get($filepath);

        $this->result = [
            'filename' => $this->filename,
            'filepath' => $filepath,
            'relative_path' => $this->getRelativePath($filepath),
            'type' => $type,
            'timestamp' => $this->extractTimestamp(),
            'name' => $this->extractMigrationName(),
            'tables' => $this->extractTables(),
            'ddl_operations' => $this->extractDDLOperations(),
            'dml_operations' => $this->extractDMLOperations(),
            'raw_sql' => $this->extractRawSQL(),
            'dependencies' => $this->extractDependencies(),
            'columns' => $this->extractColumns(),
            'indexes' => $this->extractIndexes(),
            'foreign_keys' => $this->extractForeignKeys(),
            'methods_used' => $this->extractMethodsUsed(),
            'has_data_modifications' => $this->hasDataModifications(),
            'complexity' => $this->calculateComplexity(),
        ];

        return $this->result;
    }
    
    protected function extractTimestamp(): string
    {
        if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_/', $this->filename, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }
    
    protected function extractMigrationName(): string
    {
        return preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', 
                           str_replace('.php', '', $this->filename));
    }
    
    protected function extractTables(): array
    {
        $tables = [];

        // Schema::create
        if (preg_match_all('/Schema::create\s*\(\s*[\'"]([^"\']+)[\'"]/', $this->content, $matches)) {
            foreach ($matches[1] as $table) {
                $tables[$table] = ['operation' => 'CREATE', 'methods' => []];
            }
        }

        // Schema::table (ALTER)
        if (preg_match_all('/Schema::table\s*\(\s*[\'"]([^"\']+)[\'"]/', $this->content, $matches)) {
            foreach ($matches[1] as $table) {
                if (!isset($tables[$table])) {
                    $tables[$table] = ['operation' => 'ALTER', 'methods' => []];
                }
            }
        }

        // Schema::drop
        if (preg_match_all('/Schema::drop(?:IfExists)?\s*\(\s*[\'"]([^"\']+)[\'"]/', $this->content, $matches)) {
            foreach ($matches[1] as $table) {
                $tables[$table] = ['operation' => 'DROP', 'methods' => []];
            }
        }

        // Schema::rename
        if (preg_match_all('/Schema::rename\s*\(\s*[\'"]([^"\']+)[\'"]/', $this->content, $matches)) {
            foreach ($matches[1] as $table) {
                $tables[$table] = ['operation' => 'RENAME', 'methods' => []];
            }
        }

        // DB::table (data operations)
        if (preg_match_all('/DB::table\s*\(\s*[\'"]([^"\']+)[\'"]/', $this->content, $matches)) {
            foreach ($matches[1] as $table) {
                if (!isset($tables[$table])) {
                    $tables[$table] = ['operation' => 'DATA', 'methods' => []];
                }
            }
        }

        return $tables;
    }
    
    protected function extractDDLOperations(): array
    {
        $operations = [];

        $blueprintMethods = [
            'id', 'foreignId', 'bigIncrements', 'bigInteger', 'binary', 'boolean',
            'char', 'dateTimeTz', 'dateTime', 'date', 'decimal', 'double',
            'enum', 'float', 'foreignUuid', 'geometryCollection', 'geometry',
            'increments', 'integer', 'ipAddress', 'json', 'jsonb', 'lineString',
            'longText', 'macAddress', 'mediumIncrements', 'mediumInteger',
            'mediumText', 'morphs', 'multiLineString', 'multiPoint', 'multiPolygon',
            'nullableMorphs', 'nullableTimestamps', 'nullableUuidMorphs', 'point',
            'polygon', 'rememberToken', 'set', 'smallIncrements', 'smallInteger',
            'softDeletesTz', 'softDeletes', 'string', 'text', 'timeTz', 'time',
            'timestampTz', 'timestamp', 'timestampsTz', 'timestamps', 'tinyIncrements',
            'tinyInteger', 'tinyText', 'unsignedBigInteger', 'unsignedDecimal',
            'unsignedInteger', 'unsignedMediumInteger', 'unsignedSmallInteger',
            'unsignedTinyInteger', 'uuidMorphs', 'uuid', 'year',
            'addColumn', 'dropColumn', 'renameColumn', 'modifyColumn',
            'index', 'unique', 'primary', 'foreign', 'dropIndex', 'dropUnique',
            'dropPrimary', 'dropForeign'
        ];

        foreach ($blueprintMethods as $method) {
            $pattern = '/\$table->' . preg_quote($method) . '\s*\(([^)]*)\)/';
            if (preg_match_all($pattern, $this->content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $params = $this->parseMethodParams($match[1]);
                    $operations[] = [
                        'method' => $method,
                        'params' => $params,
                        'category' => $this->categorizeMethod($method)
                    ];
                }
            }
        }

        return $operations;
    }
    
    protected function extractDMLOperations(): array
    {
        $operations = [];

        // 1. DB::table()->update - BASIC + WITH DB::raw
        if (preg_match_all(
            '/DB::table\([\'"]([^"\']+)[\'"]\)((?:[^;])+?)->update\s*\(\s*(\[[^\]]*\])/s',
            $this->content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $table = $match[1];
                $chainedMethods = $match[2];
                $updateData = $match[3];
                
                // Check if there are DB::raw in update
                $hasDbRaw = strpos($updateData, 'DB::raw') !== false;
                $dbRawSql = [];
                if ($hasDbRaw) {
                    // Extract DB::raw content
                    if (preg_match_all('/DB::raw\s*\(\s*["\'](.+?)["\']\s*\)/s', $updateData, $rawMatches)) {
                        $dbRawSql = $rawMatches[1];
                    }
                }
                
                $operations[] = [
                    'type' => 'UPDATE',
                    'table' => $table,
                    'where_conditions' => $this->extractWhereConditions($chainedMethods),
                    'columns_updated' => $this->extractColumnsFromArray($updateData),
                    'has_db_raw' => $hasDbRaw,
                    'db_raw_expressions' => $dbRawSql,
                    'data_preview' => $this->cleanupDataPreview($updateData, 150)
                ];
            }
        }

        // 2. DB::table()->insert
        if (preg_match_all(
            '/DB::table\([\'"]([^"\']+)[\'"]\)((?:[^;])+?)->insert\s*\(([^)]*)\)/s',
            $this->content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $table = $match[1];
                $chainedMethods = $match[2];
                $insertData = $match[3];
                
                $operations[] = [
                    'type' => 'INSERT',
                    'table' => $table,
                    'where_conditions' => $this->extractWhereConditions($chainedMethods),
                    'data_preview' => $this->cleanupDataPreview($insertData, 150)
                ];
            }
        }

        // 3. DB::table()->delete
        if (preg_match_all(
            '/DB::table\([\'"]([^"\']+)[\'"]\)((?:[^;])+?)->delete\s*\(\)/s',
            $this->content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $table = $match[1];
                $chainedMethods = $match[2];
                
                $operations[] = [
                    'type' => 'DELETE',
                    'table' => $table,
                    'where_conditions' => $this->extractWhereConditions($chainedMethods)
                ];
            }
        }

        // 4. Model::create - static call
        if (preg_match_all(
            '/\\\\?App\\\\[^:]+::create\s*\(/s',
            $this->content,
            $matches
        )) {
            foreach ($matches[0] as $match) {
                // Extract model name
                if (preg_match('/\\\\([A-Z][a-zA-Z]+)::create/', $match, $modelMatch)) {
                    $operations[] = [
                        'type' => 'INSERT',
                        'model' => $modelMatch[1],
                        'method' => 'Eloquent::create',
                        'note' => 'Static Model::create() call'
                    ];
                }
            }
        }

        // 5. ->save() - model instances
        if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)->save\s*\(\)/', $this->content, $matches)) {
            $savedVariables = array_unique($matches[1]);
            foreach ($savedVariables as $var) {
                $operations[] = [
                    'type' => 'UPDATE/INSERT',
                    'variable' => '$' . $var,
                    'method' => 'Eloquent->save()',
                    'note' => 'Model save - may be INSERT or UPDATE'
                ];
            }
        }

        // 6. ->create() na relacjach
        if (preg_match_all(
            '/\$([a-zA-Z_][a-zA-Z0-9_]*)->([a-zA-Z_][a-zA-Z0-9_]*)\(\)->create(?:Many)?\s*\(/s',
            $this->content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $variable = '$' . $match[1];
                $relation = $match[2];
                $operations[] = [
                    'type' => 'INSERT',
                    'variable' => $variable,
                    'relation' => $relation,
                    'method' => 'Eloquent->relation()->create()',
                    'note' => "Record creation through {$relation} relationship"
                ];
            }
        }

        // 7. ->delete() na modelach
        if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)->(?:each->)?delete\s*\(\)/', $this->content, $matches)) {
            $deletedVariables = array_unique($matches[1]);
            foreach ($deletedVariables as $var) {
                $operations[] = [
                    'type' => 'DELETE',
                    'variable' => '$' . $var,
                    'method' => 'Eloquent->delete()',
                    'note' => 'Model/collection deletion'
                ];
            }
        }

        // 8. Loop operations - detect foreach/while with operations
        if (preg_match_all(
            '/foreach\s*\([^)]+\)\s*\{([^}]+(?:\{[^}]+\}[^}]*)*)\}/s',
            $this->content,
            $matches
        )) {
            foreach ($matches[1] as $loopBody) {
                $loopOperations = [];
                
                // Look for ->save() in loop
                if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)->save\s*\(\)/', $loopBody, $saveMatches)) {
                    $loopOperations[] = 'save() na $' . implode(', $', array_unique($saveMatches[1]));
                }
                
                // Look for ->create() in loop
                if (preg_match('/->create(?:Many)?\s*\(/', $loopBody)) {
                    $loopOperations[] = 'create()';
                }
                
                // Look for ->delete() in loop
                if (preg_match('/->delete\s*\(\)/', $loopBody)) {
                    $loopOperations[] = 'delete()';
                }
                
                // Look for ->update() in loop
                if (preg_match('/->update\s*\(/', $loopBody)) {
                    $loopOperations[] = 'update()';
                }
                
                if (!empty($loopOperations)) {
                    $operations[] = [
                        'type' => 'LOOP',
                        'method' => 'foreach',
                        'operations_in_loop' => $loopOperations,
                        'note' => 'Loop operations: ' . implode(', ', $loopOperations)
                    ];
                }
            }
        }

        return $operations;
    }
    
    protected function extractWhereConditions(string $chainedMethods): array
    {
        $conditions = [];

        // where('column', 'value') or where('column', '=', 'value')
        if (preg_match_all(
            '/->where\s*\(\s*[\'"]([^"\']+)[\'"](?:\s*,\s*[\'"]?([^"\'\),]+)[\'"]?)?(?:\s*,\s*[\'"]?([^"\'\)]+)[\'"]?)?\)/s',
            $chainedMethods,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $column = $match[1];
                $operator = isset($match[3]) ? trim($match[2]) : '=';
                $value = isset($match[3]) ? trim($match[3]) : (isset($match[2]) ? trim($match[2]) : 'unknown');
                
                // Truncate long values
                if (strlen($value) > 50) {
                    $value = substr($value, 0, 50) . '...';
                }
                
                $conditions[] = "{$column} {$operator} {$value}";
            }
        }

        // whereIn('column', [values])
        if (preg_match_all('/->whereIn\s*\(\s*[\'"]([^"\']+)[\'"]/', $chainedMethods, $matches)) {
            foreach ($matches[1] as $column) {
                $conditions[] = "{$column} IN (...)";
            }
        }

        // whereNotIn
        if (preg_match_all('/->whereNotIn\s*\(\s*[\'"]([^"\']+)[\'"]/', $chainedMethods, $matches)) {
            foreach ($matches[1] as $column) {
                $conditions[] = "{$column} NOT IN (...)";
            }
        }

        // whereNull, whereNotNull
        if (preg_match_all('/->where(Not)?Null\s*\(\s*[\'"]([^"\']+)[\'"]/', $chainedMethods, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $isNot = !empty($match[1]);
                $column = $match[2];
                $conditions[] = $column . ($isNot ? ' IS NOT NULL' : ' IS NULL');
            }
        }

        // whereBetween
        if (preg_match_all('/->whereBetween\s*\(\s*[\'"]([^"\']+)[\'"]/', $chainedMethods, $matches)) {
            foreach ($matches[1] as $column) {
                $conditions[] = "{$column} BETWEEN (...)";
            }
        }

        // whereHas - relationships
        if (preg_match_all('/->whereHas\s*\(\s*[\'"]([^"\']+)[\'"]/', $chainedMethods, $matches)) {
            foreach ($matches[1] as $relation) {
                $conditions[] = "HAS {$relation}";
            }
        }

        // whereDoesntHave - missing relationship
        if (preg_match_all('/->whereDoesntHave\s*\(\s*[\'"]([^"\']+)[\'"]/', $chainedMethods, $matches)) {
            foreach ($matches[1] as $relation) {
                $conditions[] = "DOESN'T HAVE {$relation}";
            }
        }

        // orWhere
        if (preg_match_all(
            '/->orWhere\s*\(\s*[\'"]([^"\']+)[\'"](?:\s*,\s*[\'"]?([^"\'\),]+)[\'"]?)?(?:\s*,\s*[\'"]?([^"\'\)]+)[\'"]?)?\)/s',
            $chainedMethods,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $column = $match[1];
                $operator = isset($match[3]) ? trim($match[2]) : '=';
                $value = isset($match[3]) ? trim($match[3]) : (isset($match[2]) ? trim($match[2]) : 'unknown');
                
                if (strlen($value) > 50) {
                    $value = substr($value, 0, 50) . '...';
                }
                
                $conditions[] = "OR {$column} {$operator} {$value}";
            }
        }

        return $conditions;
    }
    
    protected function extractColumnsFromArray(string $arrayContent): array
    {
        $columns = [];
        
        // Look for keys in array: 'column_name' => value
        if (preg_match_all('/[\'"]([a-zA-Z_][a-zA-Z0-9_]*)[\'"][\s]*=>/', $arrayContent, $matches)) {
            $columns = array_unique($matches[1]);
        }

        return $columns;
    }
    
    protected function cleanupDataPreview(string $data, int $maxLength = 100): string
    {
        $data = trim($data);
        
        // Remove excessive whitespace
        $data = preg_replace('/\s+/', ' ', $data);
        
        if (strlen($data) > $maxLength) {
            $data = substr($data, 0, $maxLength) . '...';
        }
        
        return $data;
    }
    
    protected function extractRawSQL(): array
    {
        $sql = [];

        // DB::statement - extract full query
        if (preg_match_all('/DB::statement\s*\(\s*(["\'])(.+?)\1\s*(?:,|\))/s', $this->content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statement = $match[2];
                $sql[] = [
                    'type' => 'statement',
                    'sql' => $this->formatSQL($statement),
                    'operation' => $this->detectSQLOperation($statement)
                ];
            }
        }

        // DB::unprepared - often used for long SQL
        if (preg_match_all('/DB::unprepared\s*\(\s*(["\'])(.+?)\1\s*(?:,|\))/s', $this->content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statement = $match[2];
                $sql[] = [
                    'type' => 'unprepared',
                    'sql' => $this->formatSQL($statement),
                    'operation' => $this->detectSQLOperation($statement)
                ];
            }
        }

        // DB::raw in select/where - these are usually fragments
        if (preg_match_all('/DB::raw\s*\(\s*(["\'])(.+?)\1\s*\)/s', $this->content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statement = $match[2];
                $sql[] = [
                    'type' => 'raw',
                    'sql' => $this->formatSQL($statement),
                    'operation' => 'EXPRESSION'
                ];
            }
        }

        // Heredoc/Nowdoc SQL - often used for long queries
        if (preg_match_all('/<<<(["\']?)SQL\1\s*(.+?)\s*SQL/s', $this->content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statement = $match[2];
                $sql[] = [
                    'type' => 'heredoc',
                    'sql' => $this->formatSQL($statement),
                    'operation' => $this->detectSQLOperation($statement)
                ];
            }
        }

        return $sql;
    }
    
    protected function formatSQL(string $sql): string
    {
        // Remove leading/trailing whitespace
        $sql = trim($sql);
        
        // Replace multiple spaces with single
        $sql = preg_replace('/\s+/', ' ', $sql);
        
        // Truncate if very long (but show more than before)
        if (strlen($sql) > 500) {
            $sql = substr($sql, 0, 500) . '... [truncated]';
        }
        
        return $sql;
    }
    
    protected function detectSQLOperation(string $sql): string
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
    
    protected function extractDependencies(): array
    {
        $dependencies = [];

        // Look for comments like "Requires: ", "Depends on: ", etc.
        if (preg_match_all('/@requires?\s+([^\s\n]+)/', $this->content, $matches)) {
            foreach ($matches[1] as $dep) {
                $dependencies['requires'][] = $dep;
            }
        }

        if (preg_match_all('/@depends?\s+on\s+([^\s\n]+)/', $this->content, $matches)) {
            foreach ($matches[1] as $dep) {
                $dependencies['depends_on'][] = $dep;
            }
        }

        // Look for foreign keys - these are also dependencies
        if (preg_match_all('/->foreign\s*\([\'"]([^"\']+)[\'"]\)\s*->references\s*\([\'"]([^"\']+)[\'"]\)\s*->on\s*\([\'"]([^"\']+)[\'"]/', 
                           $this->content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $dependencies['foreign_keys'][] = [
                    'column' => $match[1],
                    'references' => $match[2],
                    'on_table' => $match[3]
                ];
            }
        }

        return $dependencies;
    }
    
    protected function extractColumns(): array
    {
        $columns = [];

        // All calls of type $table->type('name')
        $columnTypes = [
            'string', 'integer', 'bigInteger', 'text', 'boolean', 'timestamp',
            'datetime', 'date', 'decimal', 'float', 'json', 'enum', 'uuid',
            'foreignId', 'id', 'increments', 'bigIncrements'
        ];

        foreach ($columnTypes as $type) {
            $pattern = '/\$table->' . preg_quote($type) . '\s*\(\s*[\'"]([^"\']+)[\'"]([^)]*)\)/';
            if (preg_match_all($pattern, $this->content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $columnName = $match[1];
                    $modifiers = $this->extractColumnModifiers($match[0]);
                    
                    $columns[$columnName] = [
                        'type' => $type,
                        'modifiers' => $modifiers
                    ];
                }
            }
        }

        return $columns;
    }
    
    protected function extractColumnModifiers(string $columnDefinition): array
    {
        $modifiers = [];

        if (strpos($columnDefinition, '->nullable()') !== false) {
            $modifiers[] = 'nullable';
        }
        if (preg_match('/->default\(([^)]+)\)/', $columnDefinition, $matches)) {
            $modifiers[] = 'default(' . trim($matches[1]) . ')';
        }
        if (strpos($columnDefinition, '->unique()') !== false) {
            $modifiers[] = 'unique';
        }
        if (strpos($columnDefinition, '->unsigned()') !== false) {
            $modifiers[] = 'unsigned';
        }
        if (strpos($columnDefinition, '->index()') !== false) {
            $modifiers[] = 'indexed';
        }
        if (strpos($columnDefinition, '->primary()') !== false) {
            $modifiers[] = 'primary';
        }

        return $modifiers;
    }
    
    protected function extractIndexes(): array
    {
        $indexes = [];

        // ->index()
        if (preg_match_all('/->index\s*\(\s*([^)]+)\)/', $this->content, $matches)) {
            foreach ($matches[1] as $indexDef) {
                $indexes[] = ['type' => 'index', 'definition' => trim($indexDef)];
            }
        }

        // ->unique()
        if (preg_match_all('/->unique\s*\(\s*([^)]+)\)/', $this->content, $matches)) {
            foreach ($matches[1] as $indexDef) {
                $indexes[] = ['type' => 'unique', 'definition' => trim($indexDef)];
            }
        }

        return $indexes;
    }
    
    protected function extractForeignKeys(): array
    {
        $foreignKeys = [];

        if (preg_match_all('/->foreign\s*\(\s*[\'"]([^"\']+)[\'"]\s*\)(?:\s*->references\s*\(\s*[\'"]([^"\']+)[\'"]\s*\))?(?:\s*->on\s*\(\s*[\'"]([^"\']+)[\'"]\s*\))?/',
                           $this->content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $foreignKeys[] = [
                    'column' => $match[1],
                    'references' => $match[2] ?? null,
                    'on_table' => $match[3] ?? null
                ];
            }
        }

        return $foreignKeys;
    }
    
    protected function extractMethodsUsed(): array
    {
        $methods = [];

        if (preg_match_all('/\$table->([a-zA-Z_]+)\s*\(/', $this->content, $matches)) {
            $methods = array_unique($matches[1]);
        }

        return array_values($methods);
    }
    
    protected function hasDataModifications(): bool
    {
        return !empty($this->extractDMLOperations()) || 
               !empty($this->extractRawSQL()) ||
               strpos($this->content, 'DB::table') !== false ||
               strpos($this->content, '::create(') !== false ||
               strpos($this->content, '::update(') !== false ||
               strpos($this->content, '::insert(') !== false;
    }
    
    protected function calculateComplexity(): int
    {
        $score = 0;

        // Number of tables
        $score += count($this->result['tables'] ?? []);

        // Number of DDL operations
        $score += count($this->result['ddl_operations'] ?? []) * 0.5;

        // DML operations are more complex
        $score += count($this->result['dml_operations'] ?? []) * 2;

        // Raw SQL is the most risky
        $score += count($this->result['raw_sql'] ?? []) * 3;

        // Foreign keys add complexity
        $score += count($this->result['foreign_keys'] ?? []) * 1.5;

        return min(10, max(1, (int) round($score)));
    }
    
    protected function categorizeMethod(string $method): string
    {
        $categories = [
            'column_create' => [
                'id', 'string', 'integer', 'text', 'boolean', 'timestamp', 'datetime',
                'date', 'decimal', 'float', 'json', 'enum', 'uuid', 'foreignId'
            ],
            'column_modify' => ['addColumn', 'dropColumn', 'renameColumn', 'modifyColumn'],
            'index' => ['index', 'unique', 'primary'],
            'index_drop' => ['dropIndex', 'dropUnique', 'dropPrimary'],
            'foreign_key' => ['foreign'],
            'foreign_key_drop' => ['dropForeign']
        ];

        foreach ($categories as $category => $methods) {
            if (in_array($method, $methods)) {
                return $category;
            }
        }

        return 'other';
    }
    
    protected function parseMethodParams(string $params): array
    {
        $params = trim($params);
        if (empty($params)) {
            return [];
        }

        // Simple heuristic - split by commas (may not work for nested arrays)
        $parts = explode(',', $params);
        return array_map('trim', $parts);
    }
    
    protected function getRelativePath(string $filepath): string
    {
        // Remove base_path() from the beginning
        $basePath = base_path();
        if (strpos($filepath, $basePath) === 0) {
            return ltrim(substr($filepath, strlen($basePath)), '/');
        }
        return $filepath;
    }
}
