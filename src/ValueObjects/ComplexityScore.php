<?php

namespace DevSite\LaravelMigrationSearcher\ValueObjects;

use DevSite\LaravelMigrationSearcher\Contracts\Support\ScalarValueObject;

final readonly class ComplexityScore implements ScalarValueObject
{
    public int $value;

    public function __construct(int $value)
    {
        if ($value < 1 || $value > 10) {
            throw new \InvalidArgumentException(
                "Complexity score must be between 1 and 10, got {$value}"
            );
        }

        $this->value = $value;
    }

    public function toScalar(): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
