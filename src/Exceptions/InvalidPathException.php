<?php

namespace DevSite\LaravelMigrationSearcher\Exceptions;

class InvalidPathException extends \InvalidArgumentException
{
    public static function invalidBasePath(string $path): self
    {
        return new self('Base path must be a valid directory.');
    }
}
