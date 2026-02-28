<?php

namespace DevSite\LaravelMigrationSearcher\Contracts;

interface PathValidator
{
    public function isWithinBasePath(string $path): bool;

    public function normalize(string $path): string;
}
