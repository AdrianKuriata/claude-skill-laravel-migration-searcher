<?php

namespace DevSite\LaravelMigrationSearcher\Parsers;

use DevSite\LaravelMigrationSearcher\Contracts\Parsers\DdlParser as DdlParserContract;
use DevSite\LaravelMigrationSearcher\DTOs\ColumnDefinition;
use DevSite\LaravelMigrationSearcher\DTOs\DdlOperation;
use DevSite\LaravelMigrationSearcher\DTOs\ForeignKeyDefinition;
use DevSite\LaravelMigrationSearcher\DTOs\IndexDefinition;
use DevSite\LaravelMigrationSearcher\Enums\DdlCategory;

class DdlParser implements DdlParserContract
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

    /** @return DdlOperation[] */
    public function parse(string $content): array
    {
        return $this->extractDDLOperations($content);
    }

    /** @return DdlOperation[] */
    public function extractDDLOperations(string $content): array
    {
        $operations = [];

        foreach (self::BLUEPRINT_METHODS as $method) {
            $pattern = '/\$table->' . preg_quote($method) . '\s*\(([^)]*)\)/';
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $params = $this->parseMethodParams($match[1]);
                    $operations[] = new DdlOperation(
                        $method,
                        $params,
                        $this->categorizeMethod($method),
                    );
                }
            }
        }

        return $operations;
    }

    public function categorizeMethod(string $method): DdlCategory
    {
        foreach (self::CATEGORIES as $category => $methods) {
            if (in_array($method, $methods)) {
                return DdlCategory::from($category);
            }
        }

        return DdlCategory::OTHER;
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

    /** @return array<string, ColumnDefinition> */
    public function extractColumns(string $content): array
    {
        $columns = [];

        foreach (self::COLUMN_TYPES as $type) {
            $pattern = '/\$table->' . preg_quote($type) . '\s*\(\s*[\'"]([^"\']+)[\'"]([^)]*)\)/';
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $columnName = $match[1];
                    $modifiers = $this->extractColumnModifiers($match[0]);
                    $columns[$columnName] = new ColumnDefinition($type, $modifiers);
                }
            }
        }

        return $columns;
    }

    public function extractColumnModifiers(string $columnDefinition): array
    {
        $modifiers = [];

        if (str_contains($columnDefinition, '->nullable()')) {
            $modifiers[] = 'nullable';
        }
        if (preg_match('/->default\(([^)]+)\)/', $columnDefinition, $matches)) {
            $modifiers[] = 'default(' . trim($matches[1]) . ')';
        }
        if (str_contains($columnDefinition, '->unique()')) {
            $modifiers[] = 'unique';
        }
        if (str_contains($columnDefinition, '->unsigned()')) {
            $modifiers[] = 'unsigned';
        }
        if (str_contains($columnDefinition, '->index()')) {
            $modifiers[] = 'indexed';
        }
        if (str_contains($columnDefinition, '->primary()')) {
            $modifiers[] = 'primary';
        }

        return $modifiers;
    }

    /** @return IndexDefinition[] */
    public function extractIndexes(string $content): array
    {
        $indexes = [];

        if (preg_match_all('/->index\s*\(\s*([^)]+)\)/', $content, $matches)) {
            foreach ($matches[1] as $indexDef) {
                $indexes[] = new IndexDefinition('index', trim($indexDef));
            }
        }

        if (preg_match_all('/->unique\s*\(\s*([^)]+)\)/', $content, $matches)) {
            foreach ($matches[1] as $indexDef) {
                $indexes[] = new IndexDefinition('unique', trim($indexDef));
            }
        }

        return $indexes;
    }

    /** @return ForeignKeyDefinition[] */
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
                $foreignKeys[] = new ForeignKeyDefinition(
                    $match[1],
                    $match[2] ?? null,
                    $match[3] ?? null,
                );
            }
        }

        return $foreignKeys;
    }

    /** @return string[] */
    public function extractMethodsUsed(string $content): array
    {
        $methods = [];

        if (preg_match_all('/\$table->([a-zA-Z_]+)\s*\(/', $content, $matches)) {
            $methods = array_unique($matches[1]);
        }

        return array_values($methods);
    }
}
