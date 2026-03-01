<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Services;

interface TextSanitizer
{
    public function sanitize(string $value): string;
}
