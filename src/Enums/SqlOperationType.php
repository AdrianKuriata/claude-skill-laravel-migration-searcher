<?php

namespace DevSite\LaravelMigrationSearcher\Enums;

enum SqlOperationType: string
{
    case SELECT = 'SELECT';
    case INSERT = 'INSERT';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE';
    case CREATE = 'CREATE';
    case ALTER = 'ALTER';
    case DROP = 'DROP';
    case TRUNCATE = 'TRUNCATE';
    case EXPRESSION = 'EXPRESSION';
    case OTHER = 'OTHER';
}
