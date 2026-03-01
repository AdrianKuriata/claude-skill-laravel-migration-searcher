<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Services;

interface IndexGenerator
{
    /**
     * @param list<array<string, mixed>> $migrations
     * @return array<string, string>
     */
    public function generateAll(array $migrations): array;
}
