<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Services;

interface IndexDataBuilder
{
    /**
     * @param list<array<string, mixed>> $migrations
     * @return array<string, mixed>
     */
    public function buildFullIndex(array $migrations): array;

    /**
     * @param list<array<string, mixed>> $migrations
     * @return array<string, mixed>
     */
    public function buildByTypeIndex(array $migrations): array;

    /**
     * @param list<array<string, mixed>> $migrations
     * @return array<string, mixed>
     */
    public function buildByTableIndex(array $migrations): array;

    /**
     * @param list<array<string, mixed>> $migrations
     * @return array<string, mixed>
     */
    public function buildByOperationIndex(array $migrations): array;

    /**
     * @param list<array<string, mixed>> $migrations
     * @return array<string, mixed>
     */
    public function buildStats(array $migrations): array;
}
