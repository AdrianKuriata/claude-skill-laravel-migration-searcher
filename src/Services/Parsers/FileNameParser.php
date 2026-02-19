<?php

namespace DevSite\LaravelMigrationSearcher\Services\Parsers;

class FileNameParser
{
    public function extractTimestamp(string $filename): string
    {
        if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_/', $filename, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }

    public function extractMigrationName(string $filename): string
    {
        return preg_replace(
            '/^\d{4}_\d{2}_\d{2}_\d{6}_/',
            '',
            str_replace('.php', '', $filename)
        );
    }

    public function getRelativePath(string $filepath): string
    {
        $basePath = base_path();

        if (strpos($filepath, $basePath) === 0) {
            return ltrim(substr($filepath, strlen($basePath)), '/');
        }

        return $filepath;
    }
}
