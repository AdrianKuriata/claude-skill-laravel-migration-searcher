<?php

namespace Tests;

use DevSite\LaravelMigrationSearcher\MigrationSearcherServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            MigrationSearcherServiceProvider::class,
        ];
    }

    protected function getFixturePath(string $filename = ''): string
    {
        $path = __DIR__ . '/fixtures/migrations';

        if ($filename) {
            $path .= '/' . $filename;
        }

        return $path;
    }
}
