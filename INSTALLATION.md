# Installation Instructions

## For Your Project (with v2→v3 workflow)

### Step 1: Install Package

If publishing to Packagist:
```bash
composer require devsite/claude-skill-laravel-migration-searcher
```

If installing locally (for development):
```bash
# Copy this package directory to your Laravel project
# Then in your project's composer.json:
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/laravel-migration-searcher"
        }
    ],
    "require": {
        "devsite/claude-skill-laravel-migration-searcher": "@dev"
    }
}

composer update
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=migration-searcher-config
```

This creates `config/migration-searcher.php`

### Step 3: Configure for Your Project

Replace content of `config/migration-searcher.php` with:

```php
<?php

return [
    'output_path' => '.claude/skills/laravel-migration-searcher',

    'migration_types' => [
        'system' => [
            'path' => 'database/migrations',
        ],
    ],

    'skill_template_path' => __DIR__.'/../vendor/devsite/claude-skill-laravel-migration-searcher/resources/skill-template/SKILL.md',
];
```

### Step 4: Generate Index

```bash
php artisan migrations:index
```

Output:
```
🔍 Starting Laravel migration indexing...

📂 Indexing migrations: system
   [████████████████████] 312/312 (100%) Analyzing...
   Found: 312 migrations

📂 Indexing migrations: instances
   [████████████████████] 1398/1398 (100%) Analyzing...
   Found: 1398 migrations

📂 Indexing migrations: before
   [████████████████████] 18/18 (100%) Analyzing...
   Found: 18 migrations

📂 Indexing migrations: after
   [████████████████████] 6/6 (100%) Analyzing...
   Found: 6 migrations

📊 Total found: 1734 migrations

📝 Generating index files...

✅ Generated files:
   - full: .claude/skills/laravel-migration-searcher/index-full.md (456 KB)
   - by_type: .claude/skills/laravel-migration-searcher/index-by-type.md (234 KB)
   - by_table: .claude/skills/laravel-migration-searcher/index-by-table.md (389 KB)
   - by_operation: .claude/skills/laravel-migration-searcher/index-by-operation.md (298 KB)
   - stats: .claude/skills/laravel-migration-searcher/stats.json (12 KB)

⏱️  Execution time: 34.52s
```

### Step 5: Commit to Git

```bash
git add .claude/ config/migration-searcher.php
git commit -m "Add Laravel Migration Searcher package"
git push
```

### Step 6: Upload to Claude

Upload all files from `.claude/skills/laravel-migration-searcher/` to claude.ai:
- SKILL.md
- index-full.md
- index-by-type.md
- index-by-table.md
- index-by-operation.md
- stats.json

## Daily Usage

### After Adding Migration

```bash
php artisan make:migration add_new_feature
# ... write migration code ...
php artisan migrations:index --refresh
git add database/migrations/ .claude/
git commit -m "Add new feature migration"
git push
```

### Querying Claude

```
"Find the migration that adds subscription_plan column"
"Which migration deletes records from orders table?"
"Show all migrations that use DB::raw in before migrations"
```

## Configuration Options

### Index Only Specific Types

```bash
# Index only system migrations
php artisan migrations:index --type=system

# Index only instances
php artisan migrations:index --type=instances

# Index before and after
php artisan migrations:index --type=before
php artisan migrations:index --type=after
```

### Custom Output Path

```bash
php artisan migrations:index --output=/custom/path
```

### Refresh Index

```bash
# Force refresh (deletes old index first)
php artisan migrations:index --refresh
```

## Team Workflow

1. **One developer installs and configures** (you)
2. **Commits to git**
3. **Other developers pull**
4. **Everyone has access to the same index**
5. **Each developer uploads to their own Claude instance**

## Automation

Add to `composer.json`:

```json
{
    "scripts": {
        "post-autoload-dump": [
            "@php artisan migrations:index --refresh"
        ]
    }
}
```

Index will auto-refresh after `composer install/update`.

## Troubleshooting

**Q: Command not found**  
A: Run `composer dump-autoload`

**Q: Index is empty**  
A: Check paths in `config/migration-searcher.php`

**Q: Claude doesn't see the index**  
A: Verify files are uploaded to claude.ai

**Q: Index is outdated**  
A: Run `php artisan migrations:index --refresh`

---

**Enjoy fast migration searching!** 🚀
