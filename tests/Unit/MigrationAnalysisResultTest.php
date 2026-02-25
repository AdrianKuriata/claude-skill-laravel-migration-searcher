<?php

namespace Tests\Unit;

use DevSite\LaravelMigrationSearcher\DTOs\BaseDTO;
use DevSite\LaravelMigrationSearcher\DTOs\MigrationAnalysisResult;
use Illuminate\Contracts\Support\Arrayable;
use PHPUnit\Framework\TestCase;

class MigrationAnalysisResultTest extends TestCase
{
    protected function createResult(array $overrides = []): MigrationAnalysisResult
    {
        return new MigrationAnalysisResult(...array_merge([
            'filename' => '2024_01_15_100000_create_users_table.php',
            'filepath' => '/app/database/migrations/2024_01_15_100000_create_users_table.php',
            'relativePath' => 'database/migrations/2024_01_15_100000_create_users_table.php',
            'type' => 'default',
            'timestamp' => '2024_01_15_100000',
            'name' => 'create_users_table',
            'tables' => ['users' => ['operation' => 'CREATE']],
            'ddlOperations' => [['method' => 'string', 'category' => 'column_create']],
            'dmlOperations' => [],
            'rawSql' => [],
            'dependencies' => [],
            'columns' => ['name' => ['type' => 'string']],
            'indexes' => [],
            'foreignKeys' => [],
            'methodsUsed' => ['string', 'integer'],
            'hasDataModifications' => false,
            'complexity' => 2,
        ], $overrides));
    }

    public function testExtendsBaseDTO(): void
    {
        $result = $this->createResult();

        $this->assertInstanceOf(BaseDTO::class, $result);
    }

    public function testImplementsArrayable(): void
    {
        $result = $this->createResult();

        $this->assertInstanceOf(Arrayable::class, $result);
    }

    public function testPropertiesAreAccessible(): void
    {
        $result = $this->createResult();

        $this->assertSame('2024_01_15_100000_create_users_table.php', $result->filename);
        $this->assertSame('/app/database/migrations/2024_01_15_100000_create_users_table.php', $result->filepath);
        $this->assertSame('database/migrations/2024_01_15_100000_create_users_table.php', $result->relativePath);
        $this->assertSame('default', $result->type);
        $this->assertSame('2024_01_15_100000', $result->timestamp);
        $this->assertSame('create_users_table', $result->name);
        $this->assertSame(['users' => ['operation' => 'CREATE']], $result->tables);
        $this->assertSame([['method' => 'string', 'category' => 'column_create']], $result->ddlOperations);
        $this->assertSame([], $result->dmlOperations);
        $this->assertSame([], $result->rawSql);
        $this->assertSame([], $result->dependencies);
        $this->assertSame(['name' => ['type' => 'string']], $result->columns);
        $this->assertSame([], $result->indexes);
        $this->assertSame([], $result->foreignKeys);
        $this->assertSame(['string', 'integer'], $result->methodsUsed);
        $this->assertFalse($result->hasDataModifications);
        $this->assertSame(2, $result->complexity);
    }

    public function testToArrayReturnsSnakeCaseKeys(): void
    {
        $result = $this->createResult();
        $array = $result->toArray();

        $expectedKeys = [
            'filename', 'filepath', 'relative_path', 'type', 'timestamp',
            'name', 'tables', 'ddl_operations', 'dml_operations', 'raw_sql',
            'dependencies', 'columns', 'indexes', 'foreign_keys',
            'methods_used', 'has_data_modifications', 'complexity',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array, "Missing key: {$key}");
        }

        $this->assertCount(17, $array);
    }

    public function testToArrayPreservesValues(): void
    {
        $result = $this->createResult();
        $array = $result->toArray();

        $this->assertSame('2024_01_15_100000_create_users_table.php', $array['filename']);
        $this->assertSame('default', $array['type']);
        $this->assertSame(['users' => ['operation' => 'CREATE']], $array['tables']);
        $this->assertFalse($array['has_data_modifications']);
        $this->assertSame(2, $array['complexity']);
    }

    public function testIsReadonly(): void
    {
        $reflection = new \ReflectionClass(MigrationAnalysisResult::class);

        $this->assertTrue($reflection->isReadOnly());
    }

    public function testIsFinal(): void
    {
        $reflection = new \ReflectionClass(MigrationAnalysisResult::class);

        $this->assertTrue($reflection->isFinal());
    }
}
