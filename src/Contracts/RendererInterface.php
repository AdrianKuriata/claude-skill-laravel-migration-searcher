<?php

namespace DevSite\LaravelMigrationSearcher\Contracts;

interface RendererInterface
{
    public function renderFullIndex(array $data): string;

    public function renderByTypeIndex(array $data): string;

    public function renderByTableIndex(array $data): string;

    public function renderByOperationIndex(array $data): string;

    public function renderStats(array $data): string;

    public function getFileExtension(): string;
}
