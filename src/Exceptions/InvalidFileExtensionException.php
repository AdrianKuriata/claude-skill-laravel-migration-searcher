<?php

namespace DevSite\LaravelMigrationSearcher\Exceptions;

class InvalidFileExtensionException extends \InvalidArgumentException
{
    public static function create(): self
    {
        return new self('File must have a .php extension');
    }
}
