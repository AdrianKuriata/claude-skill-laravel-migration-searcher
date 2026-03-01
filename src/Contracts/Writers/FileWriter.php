<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Writers;

interface FileWriter
{
    public function write(string $path, string $content): void;

    public function ensureDirectory(string $path): void;
}
