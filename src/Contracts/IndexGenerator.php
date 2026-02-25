<?php

namespace DevSite\LaravelMigrationSearcher\Contracts;

interface IndexGenerator
{
    public function setMigrations(array $migrations): void;

    public function generateAll(): array;
}
