<?php

namespace DevSite\LaravelMigrationSearcher\Renderers;

use DevSite\LaravelMigrationSearcher\Contracts\Renderer;

class JsonRenderer implements Renderer
{
    public function getFileExtension(): string
    {
        return 'json';
    }

    public function renderFullIndex(array $data): string
    {
        return $this->encode($data);
    }

    public function renderByTypeIndex(array $data): string
    {
        return $this->encode($data);
    }

    public function renderByTableIndex(array $data): string
    {
        return $this->encode($data);
    }

    public function renderByOperationIndex(array $data): string
    {
        return $this->encode($data);
    }

    public function renderStats(array $data): string
    {
        return $this->encode($data);
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
