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
    | Custom Formats
    |--------------------------------------------------------------------------
    |
    | Register custom output formats by mapping a format name to a Renderer class.
    | These extend/override the built-in formats (markdown, json).
    |
    | Example:
    |   'formats' => [
    |       'yaml' => App\Renderers\YamlRenderer::class,
    |   ],
    |
    */
    'formats' => [],

    /*
    |--------------------------------------------------------------------------
    | Default Output Format
    |--------------------------------------------------------------------------
    |
    | The default output format for generated index files.
    | Supported: "markdown", "json"
    |
    */
    'default_format' => 'markdown',
];
