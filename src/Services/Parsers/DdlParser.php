<?php

namespace DevSite\LaravelMigrationSearcher\Services\Parsers;

use DevSite\LaravelMigrationSearcher\Contracts\ContentParserInterface;

class DdlParser implements ContentParserInterface
{
    protected const array BLUEPRINT_METHODS = [
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
        'dropPrimary', 'dropForeign',
    ];

    protected const array CATEGORIES = [
        'column_create' => [
            'id', 'string', 'integer', 'text', 'boolean', 'timestamp', 'datetime',
            'date', 'decimal', 'float', 'json', 'enum', 'uuid', 'foreignId',
        ],
        'column_modify' => ['addColumn', 'dropColumn', 'renameColumn', 'modifyColumn'],
        'index' => ['index', 'unique', 'primary'],
        'index_drop' => ['dropIndex', 'dropUnique', 'dropPrimary'],
        'foreign_key' => ['foreign'],
        'foreign_key_drop' => ['dropForeign'],
    ];

    protected const array COLUMN_TYPES = [
        'string', 'integer', 'bigInteger', 'text', 'boolean', 'timestamp',
        'datetime', 'date', 'decimal', 'float', 'json', 'enum', 'uuid',
        'foreignId', 'id', 'increments', 'bigIncrements',
    ];

    public function parse(string $content): array
    {
        return $this->extractDDLOperations($content);
    }

    public function extractDDLOperations(string $content): array
    {
        $operations = [];

        foreach (self::BLUEPRINT_METHODS as $method) {
            $pattern = '/\$table->' . preg_quote($method) . '\s*\(([^)]*)\)/';
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $params = $this->parseMethodParams($match[1]);
                    $operations[] = [
                        'method' => $method,
                        'params' => $params,
                        'category' => $this->categorizeMethod($method),
                    ];
                }
            }
        }

        return $operations;
    }

    public function categorizeMethod(string $method): string
    {
        foreach (self::CATEGORIES as $category => $methods) {
            if (in_array($method, $methods)) {
                return $category;
            }
        }

        return 'other';
    }

    public function parseMethodParams(string $params): array
    {
        $params = trim($params);

        if (empty($params)) {
            return [];
        }

        $parts = explode(',', $params);

        return array_map('trim', $parts);
    }

    public function extractColumns(string $content): array
    {
        $columns = [];

        foreach (self::COLUMN_TYPES as $type) {
            $pattern = '/\$table->' . preg_quote($type) . '\s*\(\s*[\'"]([^"\']+)[\'"]([^)]*)\)/';
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $columnName = $match[1];
                    $modifiers = $this->extractColumnModifiers($match[0]);
                    $columns[$columnName] = [
                        'type' => $type,
                        'modifiers' => $modifiers,
                    ];
                }
            }
        }

        return $columns;
    }

    public function extractColumnModifiers(string $columnDefinition): array
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

    public function extractIndexes(string $content): array
    {
        $indexes = [];

        if (preg_match_all('/->index\s*\(\s*([^)]+)\)/', $content, $matches)) {
            foreach ($matches[1] as $indexDef) {
                $indexes[] = ['type' => 'index', 'definition' => trim($indexDef)];
            }
        }

        if (preg_match_all('/->unique\s*\(\s*([^)]+)\)/', $content, $matches)) {
            foreach ($matches[1] as $indexDef) {
                $indexes[] = ['type' => 'unique', 'definition' => trim($indexDef)];
            }
        }

        return $indexes;
    }

    public function extractForeignKeys(string $content): array
    {
        $foreignKeys = [];

        if (preg_match_all(
            '/->foreign\s*\(\s*[\'"]([^"\']+)[\'"]\s*\)(?:\s*->references\s*\(\s*[\'"]([^"\']+)[\'"]\s*\))?(?:\s*->on\s*\(\s*[\'"]([^"\']+)[\'"]\s*\))?/',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $foreignKeys[] = [
                    'column' => $match[1],
                    'references' => $match[2] ?? null,
                    'on_table' => $match[3] ?? null,
                ];
            }
        }

        return $foreignKeys;
    }

    public function extractMethodsUsed(string $content): array
    {
        $methods = [];

        if (preg_match_all('/\$table->([a-zA-Z_]+)\s*\(/', $content, $matches)) {
            $methods = array_unique($matches[1]);
        }

        return array_values($methods);
    }
}
