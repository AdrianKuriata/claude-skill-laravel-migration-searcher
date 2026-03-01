<?php

namespace DevSite\LaravelMigrationSearcher\DTOs;

use DevSite\LaravelMigrationSearcher\Contracts\Support\ScalarValueObject;
use Illuminate\Contracts\Support\Arrayable;
use ReflectionClass;
use ReflectionProperty;

/** @implements Arrayable<string, mixed> */
abstract readonly class BaseDTO implements Arrayable
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = [];

        foreach ($properties as $property) {
            $key = strtolower((string) preg_replace('/[A-Z]/', '_$0', $property->getName()));
            $value = $property->getValue($this);

            $result[$key] = $this->convertValue($value);
        }

        return $result;
    }

    protected function convertValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 10) {
            return '[max depth exceeded]';
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof ScalarValueObject) {
            return $value->toScalar();
        }

        if ($value instanceof self) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->convertValue($item, $depth + 1), $value);
        }

        return $value;
    }
}
