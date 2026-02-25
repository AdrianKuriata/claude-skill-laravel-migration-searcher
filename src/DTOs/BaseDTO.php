<?php

namespace DevSite\LaravelMigrationSearcher\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use ReflectionClass;
use ReflectionProperty;

abstract readonly class BaseDTO implements Arrayable
{
    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = [];

        foreach ($properties as $property) {
            $key = strtolower(preg_replace('/[A-Z]/', '_$0', $property->getName()));
            $value = $property->getValue($this);

            $result[$key] = $value;
        }

        return $result;
    }
}
