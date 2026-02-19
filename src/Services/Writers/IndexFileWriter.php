<?php

namespace DevSite\LaravelMigrationSearcher\Services\Writers;

use DevSite\LaravelMigrationSearcher\Contracts\FileWriterInterface;
use Illuminate\Support\Facades\File;

class IndexFileWriter implements FileWriterInterface
{
    public function write(string $path, string $content): void
    {
        File::put($path, $content);
    }

    public function ensureDirectory(string $path): void
    {
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }
}
