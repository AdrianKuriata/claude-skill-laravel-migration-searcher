<?php

namespace DevSite\LaravelMigrationSearcher\Exceptions;

class FileSizeLimitExceededException extends \RuntimeException
{
    public static function create(int $fileSize, int $maxSize): self
    {
        return new self('File exceeds maximum allowed size');
    }
}
