<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Services;

interface IndexGenerator
{
    public function generateAll(array $migrations): array;
}
