<?php

namespace DevSite\LaravelMigrationSearcher\Services\Parsers;

use DevSite\LaravelMigrationSearcher\Contracts\ContentParserInterface;

class DmlParser implements ContentParserInterface
{
    public function parse(string $content): array
    {
        return $this->extractDMLOperations($content);
    }

    public function extractDMLOperations(string $content): array
    {
        $operations = [];

        $operations = array_merge($operations, $this->extractDbTableUpdates($content));
        $operations = array_merge($operations, $this->extractDbTableInserts($content));
        $operations = array_merge($operations, $this->extractDbTableDeletes($content));
        $operations = array_merge($operations, $this->extractEloquentCreates($content));
        $operations = array_merge($operations, $this->extractEloquentSaves($content));
        $operations = array_merge($operations, $this->extractEloquentRelationCreates($content));
        $operations = array_merge($operations, $this->extractEloquentDeletes($content));
        $operations = array_merge($operations, $this->extractLoopOperations($content));

        return $operations;
    }

    public function extractWhereConditions(string $chainedMethods): array
    {
        $conditions = [];

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

                if (strlen($value) > 50) {
                    $value = substr($value, 0, 50) . '...';
                }

                $conditions[] = "{$column} {$operator} {$value}";
            }
        }

        if (preg_match_all('/->whereIn\s*\(\s*[\'"]([^"\']+)[\'"]/', $chainedMethods, $matches)) {
            foreach ($matches[1] as $column) {
                $conditions[] = "{$column} IN (...)";
            }
        }

        if (preg_match_all('/->whereNotIn\s*\(\s*[\'"]([^"\']+)[\'"]/', $chainedMethods, $matches)) {
            foreach ($matches[1] as $column) {
                $conditions[] = "{$column} NOT IN (...)";
            }
        }

        if (preg_match_all('/->where(Not)?Null\s*\(\s*[\'"]([^"\']+)[\'"]/', $chainedMethods, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $isNot = !empty($match[1]);
                $column = $match[2];
                $conditions[] = $column . ($isNot ? ' IS NOT NULL' : ' IS NULL');
            }
        }

        if (preg_match_all('/->whereBetween\s*\(\s*[\'"]([^"\']+)[\'"]/', $chainedMethods, $matches)) {
            foreach ($matches[1] as $column) {
                $conditions[] = "{$column} BETWEEN (...)";
            }
        }

        if (preg_match_all('/->whereHas\s*\(\s*[\'"]([^"\']+)[\'"]/', $chainedMethods, $matches)) {
            foreach ($matches[1] as $relation) {
                $conditions[] = "HAS {$relation}";
            }
        }

        if (preg_match_all('/->whereDoesntHave\s*\(\s*[\'"]([^"\']+)[\'"]/', $chainedMethods, $matches)) {
            foreach ($matches[1] as $relation) {
                $conditions[] = "DOESN'T HAVE {$relation}";
            }
        }

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

    public function extractColumnsFromArray(string $arrayContent): array
    {
        $columns = [];

        if (preg_match_all('/[\'"]([a-zA-Z_][a-zA-Z0-9_]*)[\'"][\s]*=>/', $arrayContent, $matches)) {
            $columns = array_unique($matches[1]);
        }

        return $columns;
    }

    public function cleanupDataPreview(string $data, int $maxLength = 100): string
    {
        $data = trim($data);
        $data = preg_replace('/\s+/', ' ', $data);

        if (strlen($data) > $maxLength) {
            $data = substr($data, 0, $maxLength) . '...';
        }

        return $data;
    }

    public function hasDataModifications(string $content): bool
    {
        return !empty($this->extractDMLOperations($content)) ||
            strpos($content, 'DB::table') !== false ||
            strpos($content, '::create(') !== false ||
            strpos($content, '::update(') !== false ||
            strpos($content, '::insert(') !== false;
    }

    protected function extractDbTableUpdates(string $content): array
    {
        $operations = [];

        if (preg_match_all(
            '/DB::table\([\'"]([^"\']+)[\'"]\)((?:[^;])+?)->update\s*\(\s*(\[[^\]]*\])/s',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $table = $match[1];
                $chainedMethods = $match[2];
                $updateData = $match[3];

                $hasDbRaw = strpos($updateData, 'DB::raw') !== false;
                $dbRawSql = [];
                if ($hasDbRaw) {
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
                    'data_preview' => $this->cleanupDataPreview($updateData, 150),
                ];
            }
        }

        return $operations;
    }

    protected function extractDbTableInserts(string $content): array
    {
        $operations = [];

        if (preg_match_all(
            '/DB::table\([\'"]([^"\']+)[\'"]\)((?:[^;])+?)->insert\s*\(([^)]*)\)/s',
            $content,
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
                    'data_preview' => $this->cleanupDataPreview($insertData, 150),
                ];
            }
        }

        return $operations;
    }

    protected function extractDbTableDeletes(string $content): array
    {
        $operations = [];

        if (preg_match_all(
            '/DB::table\([\'"]([^"\']+)[\'"]\)((?:[^;])+?)->delete\s*\(\)/s',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $table = $match[1];
                $chainedMethods = $match[2];

                $operations[] = [
                    'type' => 'DELETE',
                    'table' => $table,
                    'where_conditions' => $this->extractWhereConditions($chainedMethods),
                ];
            }
        }

        return $operations;
    }

    protected function extractEloquentCreates(string $content): array
    {
        $operations = [];

        if (preg_match_all(
            '/\\\\?App\\\\[^:]+::create\s*\(/s',
            $content,
            $matches
        )) {
            foreach ($matches[0] as $match) {
                if (preg_match('/\\\\([A-Z][a-zA-Z]+)::create/', $match, $modelMatch)) {
                    $operations[] = [
                        'type' => 'INSERT',
                        'model' => $modelMatch[1],
                        'method' => 'Eloquent::create',
                        'note' => 'Static Model::create() call',
                    ];
                }
            }
        }

        return $operations;
    }

    protected function extractEloquentSaves(string $content): array
    {
        $operations = [];

        if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)->save\s*\(\)/', $content, $matches)) {
            $savedVariables = array_unique($matches[1]);
            foreach ($savedVariables as $var) {
                $operations[] = [
                    'type' => 'UPDATE/INSERT',
                    'variable' => '$' . $var,
                    'method' => 'Eloquent->save()',
                    'note' => 'Model save - may be INSERT or UPDATE',
                ];
            }
        }

        return $operations;
    }

    protected function extractEloquentRelationCreates(string $content): array
    {
        $operations = [];

        if (preg_match_all(
            '/\$([a-zA-Z_][a-zA-Z0-9_]*)->([a-zA-Z_][a-zA-Z0-9_]*)\(\)->create(?:Many)?\s*\(/s',
            $content,
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
                    'note' => "Record creation through {$relation} relationship",
                ];
            }
        }

        return $operations;
    }

    protected function extractEloquentDeletes(string $content): array
    {
        $operations = [];

        if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)->(?:each->)?delete\s*\(\)/', $content, $matches)) {
            $deletedVariables = array_unique($matches[1]);
            foreach ($deletedVariables as $var) {
                $operations[] = [
                    'type' => 'DELETE',
                    'variable' => '$' . $var,
                    'method' => 'Eloquent->delete()',
                    'note' => 'Model/collection deletion',
                ];
            }
        }

        return $operations;
    }

    protected function extractLoopOperations(string $content): array
    {
        $operations = [];

        if (preg_match_all(
            '/foreach\s*\([^)]+\)\s*\{([^}]+(?:\{[^}]+\}[^}]*)*)\}/s',
            $content,
            $matches
        )) {
            foreach ($matches[1] as $loopBody) {
                $loopOperations = [];

                if (preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)->save\s*\(\)/', $loopBody, $saveMatches)) {
                    $loopOperations[] = 'save() na $' . implode(', $', array_unique($saveMatches[1]));
                }

                if (preg_match('/->create(?:Many)?\s*\(/', $loopBody)) {
                    $loopOperations[] = 'create()';
                }

                if (preg_match('/->delete\s*\(\)/', $loopBody)) {
                    $loopOperations[] = 'delete()';
                }

                if (preg_match('/->update\s*\(/', $loopBody)) {
                    $loopOperations[] = 'update()';
                }

                if (!empty($loopOperations)) {
                    $operations[] = [
                        'type' => 'LOOP',
                        'method' => 'foreach',
                        'operations_in_loop' => $loopOperations,
                        'note' => 'Loop operations: ' . implode(', ', $loopOperations),
                    ];
                }
            }
        }

        return $operations;
    }
}
