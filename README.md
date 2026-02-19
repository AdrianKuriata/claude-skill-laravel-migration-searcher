# Laravel Migration Searcher

<p align="center">
<a href="https://packagist.org/packages/devsite/ai-claude-skill-laravel-migration-searcher"><img src="https://img.shields.io/packagist/dt/devsite/ai-claude-skill-laravel-migration-searcher" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/devsite/ai-claude-skill-laravel-migration-searcher"><img src="https://img.shields.io/packagist/v/devsite/ai-claude-skill-laravel-migration-searcher" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/devsite/ai-claude-skill-laravel-migration-searcher"><img src="https://img.shields.io/packagist/l/devsite/ai-claude-skill-laravel-migration-searcher" alt="License"></a>
</p>

Intelligent Laravel migration indexer with Claude AI integration. Automatically analyzes and indexes all migrations for instant search and debugging.

## Why This Package?

**Problem:** You have hundreds or thousands of migrations. Finding specific migrations is time-consuming and error-prone.

**Solution:** This package automatically analyzes **all** migrations and creates searchable indexes that Claude AI (or you) can query instantly.

## Features

✅ **Deep Analysis** - Analyzes DDL, DML, Raw SQL, Eloquent operations, loops  
✅ **Multiple Views** - Chronological, by-type, by-table, by-operation  
✅ **Claude AI Integration** - Built for Claude AI workflow  
✅ **Configurable** - Support for custom migration paths and types  
✅ **Team-Friendly** - Index in git, whole team benefits  
✅ **Zero Dependencies** - Only Laravel required  

## What It Analyzes

The package performs **comprehensive analysis** of each migration:

### DDL Operations (Structure)
- CREATE/ALTER/DROP tables
- Column definitions with types and modifiers
- Indexes (index, unique, primary)
- Foreign keys with dependencies

### DML Operations (Data) - v2.0
- INSERT/UPDATE/DELETE via `DB::table()`
- **WHERE conditions** - including whereIn, whereNotNull, whereHas, orWhere
- **Columns modified** - which fields are changed
- **DB::raw expressions** - full SQL (CASE WHEN, subqueries, etc.)
- **Eloquent operations** - Model::create, ->save(), ->delete()
- **Operations in loops** - foreach/while with data modifications
- **Mass operations** - whereIn with arrays of IDs

### Raw SQL
- DB::statement - complete SQL queries
- DB::unprepared - full SQL code
- DB::raw expressions
- Heredoc/Nowdoc SQL
- **Auto-detected type** (SELECT/UPDATE/DELETE/etc.)

## Installation

Install via Composer:

```bash
composer require devsite/ai-claude-skill-laravel-migration-searcher
```

Publish configuration:

```bash
php artisan vendor:publish --tag=migration-searcher-config
```

Publish skill template (optional):

```bash
php artisan vendor:publish --tag=migration-searcher-skill
```

## Configuration

Edit `config/migration-searcher.php`:

```php
return [
    // Where indexes will be generated
    'output_path' => '.claude/skills/laravel-migration-searcher',
    
    // Define your migration types
    'migration_types' => [
        'default' => [
            'path' => 'database/migrations',
        ],
        
        // Add custom types:
        // 'tenant' => [
        //     'path' => 'database/tenant-migrations',
        // ],
    ],
];
```

## Usage

### Generate Index

```bash
# Index all migrations
php artisan migrations:index

# Refresh existing index
php artisan migrations:index --refresh

# Index specific type only
php artisan migrations:index --type=default

# Custom output path
php artisan migrations:index --output=/custom/path
```

### Output

The command generates:

```
.claude/skills/laravel-migration-searcher/
├── SKILL.md                    # Instructions for Claude AI
├── index-full.md               # Chronological list
├── index-by-type.md           # Grouped by migration type
├── index-by-table.md          # Grouped by database table
├── index-by-operation.md      # Grouped by operation type
└── stats.json                 # Statistics and metadata
```

### Using with Claude AI

1. **Generate index:**
   ```bash
   php artisan migrations:index
   ```

2. **Upload to Claude:**
   - Upload all files from `.claude/skills/laravel-migration-searcher/` to claude.ai
   - Or use Claude Code with local access

3. **Ask Claude:**
   ```
   "Find the migration that adds subscription_plan column"
   "Which migration deletes records from orders?"
   "Show all migrations with DB::raw"
   ```

Claude will automatically search the index and give you exact answers with full context!

## Configuration Examples

### Multi-Tenant Application

```php
'migration_types' => [
    'system' => [
        'path' => 'database/migrations',
    ],
    'tenant' => [
        'path' => 'database/tenant-migrations',
    ],
],
```

### Modular Application

```php
'migration_types' => [
    'core' => [
        'path' => 'database/migrations',
    ],
    'modules' => [
        'path' => 'modules/*/migrations',
    ],
],
```

### Data Import Workflow

```php
'migration_types' => [
    'default' => [
        'path' => 'database/migrations',
    ],
    'import_before' => [
        'path' => 'database/import/before',
    ],
    'import_after' => [
        'path' => 'database/import/after',
    ],
],
```

## Team Workflow

### First Developer (Setup)

```bash
# 1. Install package
composer require devsite/ai-claude-skill-laravel-migration-searcher

# 2. Publish config and customize if needed
php artisan vendor:publish --tag=migration-searcher-config

# 3. Generate index
php artisan migrations:index

# 4. Commit to git
git add .claude/ config/migration-searcher.php
git commit -m "Add migration indexer"
git push
```

### Other Team Members

```bash
# 1. Pull changes
git pull

# 2. Index is already there!
# Each developer can upload to their Claude instance
```

### Daily Workflow

```bash
# After adding/modifying migrations
php artisan make:migration add_feature
php artisan migrations:index --refresh
git add database/migrations/ .claude/
git commit -m "Add feature migration"
git push
```

## Examples

### Finding a Migration

```
Q: "Which migration adds the email_verified_at column?"

A: Claude reads index-by-table.md → finds users section → responds:

Found: database/migrations/2020_01_01_000000_create_users_table.php
This migration creates the users table including email_verified_at column.
```

### Debugging Data Issues

```
Q: "After migrations, subscription dates are NULL. Find the problem."

A: Claude reads index-by-operation.md → finds UPDATE operations → responds:

Problem in: database/migrations/2023_05_12_prepare_data.php

DML Operations:
- UPDATE on users WHERE: subscription_plan IS NULL
  - Sets subscription_expires_at to NULL

This migration resets the dates! Check if it should run in your environment.
```

### Finding Complex Operations

```
Q: "Show migrations that use CASE WHEN in UPDATE"

A: Claude searches for DB::raw in UPDATE operations → responds:

Found: database/migrations/2024_01_20_calculate_status.php

Uses DB::raw:
  CASE WHEN (condition1) THEN 1 ELSE 0 END

This migration calculates status based on multiple conditions.
```

## Index Structure Example

```markdown
### 2024_01_15_add_subscription_plan.php

**Type:** default
**Path:** database/migrations/2024_01_15_add_subscription_plan.php

**Tables:**
- users (ALTER)

**DDL Operations:**
- addColumn: subscription_plan (string, nullable)
- addColumn: subscription_expires_at (timestamp, nullable)

**DML Operations:**
- **UPDATE** on `users`
  - WHERE: subscription_plan IS NULL
  - Columns: subscription_plan
  - Data: ['subscription_plan' => 'free']

**Complexity:** 3/10
```

## Requirements

- PHP 8.1 or higher
- Laravel 10.x, 11.x, or 12.x

## License

MIT License. See [LICENSE](LICENSE) for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

- Issues: [GitHub Issues](https://github.com/devsite/ai-claude-skill-laravel-migration-searcher/issues)
- Documentation: This README
- Questions: Open a discussion on GitHub

## Credits

Built for Laravel projects with large migration sets that need intelligent search and documentation.

---

**Made with ❤️ for the Laravel community**
