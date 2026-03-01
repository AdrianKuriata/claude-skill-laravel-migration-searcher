<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Renderers;

interface MarkdownMigrationFormatter
{
    public function formatMigrationFull(array $migration): string;

    public function formatMigrationCompact(array $migration): string;

    public function formatDMLSummary(array $dmlOperations): string;
}
