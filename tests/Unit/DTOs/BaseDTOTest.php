<?php

namespace Tests\Unit\DTOs;

use DevSite\LaravelMigrationSearcher\DTOs\BaseDTO;
use Illuminate\Contracts\Support\Arrayable;
use PHPUnit\Framework\TestCase;

final readonly class ConcreteDTO extends BaseDTO
{
    public function __construct(
        public string $simpleName,
        public int $camelCaseProperty,
        public array $nestedItems,
        public bool $isActive,
    ) {}
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
}
