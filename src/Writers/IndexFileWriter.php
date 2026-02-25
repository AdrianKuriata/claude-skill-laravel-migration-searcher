<?php

namespace DevSite\LaravelMigrationSearcher\Writers;

use DevSite\LaravelMigrationSearcher\Contracts\FileWriter;
use Illuminate\Support\Facades\File;

class IndexFileWriter implements FileWriter
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
