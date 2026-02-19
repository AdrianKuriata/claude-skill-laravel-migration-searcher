<?php

namespace DevSite\LaravelMigrationSearcher\Contracts;

interface FileWriterInterface
{
    public function write(string $path, string $content): void;

    public function ensureDirectory(string $path): void;
}
