<?php

namespace Tests\Unit\DTOs;

use DevSite\LaravelMigrationSearcher\DTOs\BaseDTO;
use DevSite\LaravelMigrationSearcher\DTOs\ColumnDefinition;
use DevSite\LaravelMigrationSearcher\DTOs\DdlOperation;
use DevSite\LaravelMigrationSearcher\DTOs\DependencyInfo;
use DevSite\LaravelMigrationSearcher\DTOs\MigrationAnalysisResult;
use DevSite\LaravelMigrationSearcher\DTOs\TableInfo;
use DevSite\LaravelMigrationSearcher\Enums\DdlCategory;
use DevSite\LaravelMigrationSearcher\Enums\TableOperation;
use DevSite\LaravelMigrationSearcher\ValueObjects\ComplexityScore;
use DevSite\LaravelMigrationSearcher\ValueObjects\MigrationTimestamp;
use Illuminate\Contracts\Support\Arrayable;
use PHPUnit\Framework\TestCase;

class MigrationAnalysisResultTest extends TestCase
{
    protected function createResult(array $overrides = []): MigrationAnalysisResult
    {
        $defaults = [
            'filename' => '2024_01_15_100000_create_users_table.php',
            'filepath' => '/app/database/migrations/2024_01_15_100000_create_users_table.php',
            'relativePath' => 'database/migrations/2024_01_15_100000_create_users_table.php',
            'type' => 'default',
            'timestamp' => new MigrationTimestamp('2024_01_15_100000'),
            'name' => 'create_users_table',
            'tables' => ['users' => new TableInfo(TableOperation::CREATE, [])],
            'ddlOperations' => [new DdlOperation('string', ["'name'"], DdlCategory::COLUMN_CREATE)],
            'dmlOperations' => [],
            'rawSql' => [],
            'dependencies' => new DependencyInfo(),
            'columns' => ['name' => new ColumnDefinition('string', [])],
            'indexes' => [],
            'foreignKeys' => [],
            'methodsUsed' => ['string', 'integer'],
            'hasDataModifications' => false,
            'complexity' => new ComplexityScore(2),
        ];

        return new MigrationAnalysisResult(...array_merge($defaults, $overrides));
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
        $this->assertSame('2024_01_15_100000', $result->timestamp->value);
        $this->assertSame('create_users_table', $result->name);
        $this->assertInstanceOf(TableInfo::class, $result->tables['users']);
        $this->assertSame(TableOperation::CREATE, $result->tables['users']->operation);
        $this->assertInstanceOf(DdlOperation::class, $result->ddlOperations[0]);
        $this->assertSame([], $result->dmlOperations);
        $this->assertSame([], $result->rawSql);
        $this->assertInstanceOf(DependencyInfo::class, $result->dependencies);
        $this->assertInstanceOf(ColumnDefinition::class, $result->columns['name']);
        $this->assertSame([], $result->indexes);
        $this->assertSame([], $result->foreignKeys);
        $this->assertSame(['string', 'integer'], $result->methodsUsed);
        $this->assertFalse($result->hasDataModifications);
        $this->assertSame(2, $result->complexity->value);
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

    public function testToArrayConvertsNestedDtosAndEnums(): void
    {
        $result = $this->createResult();
        $array = $result->toArray();

        $this->assertSame('2024_01_15_100000', $array['timestamp']);
        $this->assertSame(2, $array['complexity']);

        $this->assertIsArray($array['tables']['users']);
        $this->assertSame('CREATE', $array['tables']['users']['operation']);

        $this->assertIsArray($array['ddl_operations'][0]);
        $this->assertSame('string', $array['ddl_operations'][0]['method']);
        $this->assertSame('column_create', $array['ddl_operations'][0]['category']);

        $this->assertIsArray($array['dependencies']);
        $this->assertSame([], $array['dependencies']['requires']);

        $this->assertIsArray($array['columns']['name']);
        $this->assertSame('string', $array['columns']['name']['type']);
    }

    public function testToArrayPreservesSimpleValues(): void
    {
        $result = $this->createResult();
        $array = $result->toArray();

        $this->assertSame('2024_01_15_100000_create_users_table.php', $array['filename']);
        $this->assertSame('default', $array['type']);
        $this->assertFalse($array['has_data_modifications']);
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
