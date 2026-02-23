<?php

namespace DevSite\LaravelMigrationSearcher\Contracts;

interface IndexDataBuilderInterface
{
    public function buildFullIndex(array $migrations): array;

    public function buildByTypeIndex(array $migrations): array;

    public function buildByTableIndex(array $migrations): array;

    public function buildByOperationIndex(array $migrations): array;

    public function buildStats(array $migrations): array;
}
