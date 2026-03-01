<?php

namespace DevSite\LaravelMigrationSearcher\Exceptions;

class UnsupportedFormatException extends \InvalidArgumentException
{
    public static function create(string $format, array $available): self
    {
        $availableList = implode(', ', $available);

        return new self(
            "Unsupported format: '{$format}'. Available formats: {$availableList}"
        );
    }
}
