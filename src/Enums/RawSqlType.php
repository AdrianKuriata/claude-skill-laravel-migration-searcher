<?php

namespace DevSite\LaravelMigrationSearcher\Enums;

enum RawSqlType: string
{
    case STATEMENT = 'statement';
    case UNPREPARED = 'unprepared';
    case RAW = 'raw';
    case HEREDOC = 'heredoc';
}
