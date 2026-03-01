<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Support;

interface MigrationFileInfo
{
    public function extractTimestamp(string $filename): string;

    public function extractMigrationName(string $filename): string;

    public function getRelativePath(string $filepath): string;

    public function getFileSize(string $filepath): int;

    public function getContents(string $filepath): string;
}
