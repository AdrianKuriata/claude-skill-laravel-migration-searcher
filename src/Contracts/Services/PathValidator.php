<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Services;

interface PathValidator
{
    public function isWithinBasePath(string $path): bool;
}
