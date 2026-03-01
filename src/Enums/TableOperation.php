<?php

namespace DevSite\LaravelMigrationSearcher\Enums;

enum TableOperation: string
{
    case CREATE = 'CREATE';
    case ALTER = 'ALTER';
    case DROP = 'DROP';
    case RENAME = 'RENAME';
    case DATA = 'DATA';

    public function label(): string
    {
        return match ($this) {
            self::CREATE => 'Table Creation',
            self::ALTER => 'Structure Modifications',
            self::DROP => 'Table Deletion',
            self::RENAME => 'Renaming',
            self::DATA => 'Data Modifications',
        };
    }
}
