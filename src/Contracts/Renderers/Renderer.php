<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Renderers;

interface Renderer
{
    /** @param array<string, mixed> $data */
    public function renderFullIndex(array $data): string;

    /** @param array<string, mixed> $data */
    public function renderByTypeIndex(array $data): string;

    /** @param array<string, mixed> $data */
    public function renderByTableIndex(array $data): string;

    /** @param array<string, mixed> $data */
    public function renderByOperationIndex(array $data): string;

    /** @param array<string, mixed> $data */
    public function renderStats(array $data): string;

    public function getFileExtension(): string;
}
