<?php

namespace DevSite\LaravelMigrationSearcher\Contracts;

interface FileWriter
{
    public function write(string $path, string $content): void;

    public function ensureDirectory(string $path): void;
}
