<?php

namespace DevSite\LaravelMigrationSearcher\Services;

use DevSite\LaravelMigrationSearcher\Contracts\Services\PathValidator as PathValidatorContract;
use DevSite\LaravelMigrationSearcher\Exceptions\InvalidPathException;

class PathValidator implements PathValidatorContract
{
    public function __construct(
        protected string $basePath,
    ) {
        if (empty($basePath) || !is_dir($basePath)) {
            throw InvalidPathException::invalidBasePath($basePath);
        }
    }

    public function isWithinBasePath(string $path): bool
    {
        $basePath = realpath($this->basePath);
        $checkPath = dirname($this->normalize($path));

        while (true) {
            $resolvedPath = realpath($checkPath);
            if ($resolvedPath !== false) {
                return str_starts_with($resolvedPath, $basePath);
            }

            if ($checkPath === dirname($checkPath)) {
                return false; // @codeCoverageIgnore
            }

            $checkPath = dirname($checkPath);
        }
    }

    protected function normalize(string $path): string
    {
        $isAbsolute = str_starts_with($path, '/');
        $parts = array_filter(explode('/', $path), fn ($part) => $part !== '' && $part !== '.');
        $normalized = [];

        foreach ($parts as $part) {
            if ($part === '..' && !empty($normalized) && end($normalized) !== '..') {
                array_pop($normalized);
            } else {
                $normalized[] = $part;
            }
        }

        return ($isAbsolute ? '/' : '') . implode('/', $normalized);
    }
}
