<?php

namespace DevSite\LaravelMigrationSearcher\Enums;

enum DdlCategory: string
{
    case COLUMN_CREATE = 'column_create';
    case COLUMN_MODIFY = 'column_modify';
    case INDEX = 'index';
    case INDEX_DROP = 'index_drop';
    case FOREIGN_KEY = 'foreign_key';
    case FOREIGN_KEY_DROP = 'foreign_key_drop';
    case OTHER = 'other';
}
