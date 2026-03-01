<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Renderers;

interface MarkdownMigrationFormatter
{
    /** @param MigrationArray $migration */
    public function formatMigrationFull(array $migration): string;

    /** @param MigrationArray $migration */
    public function formatMigrationCompact(array $migration): string;

    /** @param list<DmlOperationArray> $dmlOperations */
    public function formatDMLSummary(array $dmlOperations): string;
}
