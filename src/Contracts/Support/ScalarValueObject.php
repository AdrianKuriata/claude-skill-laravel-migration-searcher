<?php

namespace DevSite\LaravelMigrationSearcher\Contracts\Support;

interface ScalarValueObject
{
    public function toScalar(): string|int|float|bool;
}
