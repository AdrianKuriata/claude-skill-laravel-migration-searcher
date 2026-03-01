<?php

namespace DevSite\LaravelMigrationSearcher\ValueObjects;

use DevSite\LaravelMigrationSearcher\Contracts\Support\ScalarValueObject;

final readonly class MigrationTimestamp implements ScalarValueObject
{
    public string $value;

    public function __construct(string $value)
    {
        if ($value !== 'unknown' && !preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}$/', $value)) {
            throw new \InvalidArgumentException(
                "Migration timestamp must match format YYYY_MM_DD_HHMMSS or be 'unknown', got '{$value}'"
            );
        }

        $this->value = $value;
    }

    public function isUnknown(): bool
    {
        return $this->value === 'unknown';
    }

    public function toScalar(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
