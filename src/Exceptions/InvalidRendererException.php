<?php

namespace DevSite\LaravelMigrationSearcher\Exceptions;

class InvalidRendererException extends \RuntimeException
{
    public static function notImplementingContract(string $class): self
    {
        return new self('Renderer class must implement the Renderer contract');
    }
}
