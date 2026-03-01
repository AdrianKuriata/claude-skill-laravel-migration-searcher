<?php

namespace DevSite\LaravelMigrationSearcher\Support;

use DevSite\LaravelMigrationSearcher\Contracts\Support\MigrationFileInfo as MigrationFileInfoContract;
use DevSite\LaravelMigrationSearcher\Exceptions\InvalidFileExtensionException;
use Illuminate\Support\Facades\File;

class MigrationFileInfo implements MigrationFileInfoContract
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
        $basePath = rtrim(base_path(), '/') . '/';

        if (str_starts_with($filepath, $basePath)) {
            return substr($filepath, strlen($basePath));
        }

        return $filepath;
    }

    public function getFileSize(string $filepath): int
    {
        $this->validateExtension($filepath);

        return File::size($filepath);
    }

    public function getContents(string $filepath): string
    {
        $this->validateExtension($filepath);

        return File::get($filepath);
    }

    private function validateExtension(string $filepath): void
    {
        if (pathinfo($filepath, PATHINFO_EXTENSION) !== 'php') {
            throw InvalidFileExtensionException::create();
        }
    }
}
