<?php

namespace DevSite\LaravelMigrationSearcher\Enums;

enum DmlOperationType: string
{
    case INSERT = 'INSERT';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE';
    case UPDATE_INSERT = 'UPDATE/INSERT';
    case LOOP = 'LOOP';
}
