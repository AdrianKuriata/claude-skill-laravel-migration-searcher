<?php

namespace DevSite\LaravelMigrationSearcher\Contracts;

interface IndexGeneratorInterface
{
    public function setMigrations(array $migrations): void;

    public function generateAll(): array;
}
