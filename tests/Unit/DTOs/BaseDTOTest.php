<?php

namespace Tests\Unit\DTOs;

use DevSite\LaravelMigrationSearcher\DTOs\BaseDTO;
use DevSite\LaravelMigrationSearcher\DTOs\DdlOperation;
use DevSite\LaravelMigrationSearcher\DTOs\TableInfo;
use DevSite\LaravelMigrationSearcher\Enums\DdlCategory;
use DevSite\LaravelMigrationSearcher\Enums\TableOperation;
use DevSite\LaravelMigrationSearcher\ValueObjects\ComplexityScore;
use DevSite\LaravelMigrationSearcher\ValueObjects\MigrationTimestamp;
use Illuminate\Contracts\Support\Arrayable;
use PHPUnit\Framework\TestCase;

final readonly class ConcreteDTO extends BaseDTO
{
    public function __construct(
        public string $simpleName,
        public int $camelCaseProperty,
        public array $nestedItems,
        public bool $isActive,
    ) {
    }
}

final readonly class NestedEnumDTO extends BaseDTO
{
    public function __construct(
        public TableOperation $operation,
        public DdlCategory $category,
    ) {
    }
}

final readonly class NestedDtoDTO extends BaseDTO
{
    public function __construct(
        public TableInfo $tableInfo,
        public array $operations,
    ) {
    }
}

final readonly class ValueObjectDTO extends BaseDTO
{
    public function __construct(
        public ComplexityScore $complexity,
        public MigrationTimestamp $timestamp,
    ) {
    }
}

class BaseDTOTest extends TestCase
{
    public function testImplementsArrayable(): void
    {
        $dto = new ConcreteDTO(
            simpleName: 'test',
            camelCaseProperty: 42,
            nestedItems: ['a', 'b'],
            isActive: true,
        );

        $this->assertInstanceOf(Arrayable::class, $dto);
    }

    public function testToArrayConvertsPropertiesToSnakeCase(): void
    {
        $dto = new ConcreteDTO(
            simpleName: 'test',
            camelCaseProperty: 42,
            nestedItems: ['a', 'b'],
            isActive: true,
        );

        $result = $dto->toArray();

        $this->assertArrayHasKey('simple_name', $result);
        $this->assertArrayHasKey('camel_case_property', $result);
        $this->assertArrayHasKey('nested_items', $result);
        $this->assertArrayHasKey('is_active', $result);
    }

    public function testToArrayPreservesValues(): void
    {
        $dto = new ConcreteDTO(
            simpleName: 'hello',
            camelCaseProperty: 99,
            nestedItems: [1, 2, 3],
            isActive: false,
        );

        $result = $dto->toArray();

        $this->assertSame('hello', $result['simple_name']);
        $this->assertSame(99, $result['camel_case_property']);
        $this->assertSame([1, 2, 3], $result['nested_items']);
        $this->assertFalse($result['is_active']);
    }

    public function testToArrayDoesNotIncludeNonPublicProperties(): void
    {
        $dto = new ConcreteDTO(
            simpleName: 'test',
            camelCaseProperty: 1,
            nestedItems: [],
            isActive: true,
        );

        $result = $dto->toArray();

        $this->assertCount(4, $result);
    }

    public function testToArrayConvertsEnumsToValues(): void
    {
        $dto = new NestedEnumDTO(
            operation: TableOperation::CREATE,
            category: DdlCategory::COLUMN_CREATE,
        );

        $result = $dto->toArray();

        $this->assertSame('CREATE', $result['operation']);
        $this->assertSame('column_create', $result['category']);
    }

    public function testToArrayConvertsNestedDtosToArrays(): void
    {
        $tableInfo = new TableInfo(TableOperation::ALTER, ['addColumn']);
        $operations = [
            new DdlOperation('string', ["'name'"], DdlCategory::COLUMN_CREATE),
        ];

        $dto = new NestedDtoDTO(
            tableInfo: $tableInfo,
            operations: $operations,
        );

        $result = $dto->toArray();

        $this->assertIsArray($result['table_info']);
        $this->assertSame('ALTER', $result['table_info']['operation']);
        $this->assertSame(['addColumn'], $result['table_info']['methods']);

        $this->assertIsArray($result['operations'][0]);
        $this->assertSame('string', $result['operations'][0]['method']);
        $this->assertSame('column_create', $result['operations'][0]['category']);
    }

    public function testToArrayConvertsValueObjects(): void
    {
        $dto = new ValueObjectDTO(
            complexity: new ComplexityScore(7),
            timestamp: new MigrationTimestamp('2024_01_15_100000'),
        );

        $result = $dto->toArray();

        $this->assertSame(7, $result['complexity']);
        $this->assertSame('2024_01_15_100000', $result['timestamp']);
    }

    public function testConvertValueDepthGuard(): void
    {
        $nested = 'leaf';
        for ($i = 0; $i < 15; $i++) {
            $nested = [$nested];
        }

        $dto = new ConcreteDTO(
            simpleName: 'test',
            camelCaseProperty: 1,
            nestedItems: $nested,
            isActive: true,
        );

        $result = $dto->toArray();

        $current = $result['nested_items'];
        for ($i = 0; $i < 11; $i++) {
            $this->assertIsArray($current);
            $current = $current[0];
        }

        $this->assertSame('[max depth exceeded]', $current);
    }
}
