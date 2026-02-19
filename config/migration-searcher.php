<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Output Path
    |--------------------------------------------------------------------------
    |
    | The directory where migration indexes will be generated.
    | This should be a path relative to your Laravel project root.
    | Recommended: Keep it in .claude/skills/ for Claude AI integration.
    |
    */
    'output_path' => '.claude/skills/laravel-migration-searcher',

    /*
    |--------------------------------------------------------------------------
    | Migration Types
    |--------------------------------------------------------------------------
    |
    | Define different types of migrations in your project.
    | Each type has a unique key, a filesystem path.
    |
    | Default configuration includes only the standard Laravel migrations path.
    | You can add custom types for multi-tenant apps, modular structures, etc.
    |
    */
    'migration_types' => [
        'default' => [
            'path' => 'database/migrations'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Max File Size (bytes)
    |--------------------------------------------------------------------------
    |
    | Maximum file size in bytes for a single migration file to be parsed.
    | Files exceeding this limit will be skipped with a warning.
    | Default: 5MB (5242880 bytes)
    |
    */
    'max_file_size' => 5242880,

    /*
    |--------------------------------------------------------------------------
    | Skill Template Path
    |--------------------------------------------------------------------------
    |
    | Path to the SKILL.md template that will be copied to the output directory.
    | This should not be changed unless you're customizing the package.
    |
    */
    'skill_template_path' => __DIR__.'/../resources/skill-template/SKILL.md',
];
