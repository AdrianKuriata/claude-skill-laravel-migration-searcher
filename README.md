# Laravel Migration Searcher

<p align="center">
<a href="https://packagist.org/packages/devsite/claude-skill-laravel-migration-searcher"><img src="https://img.shields.io/packagist/dt/devsite/claude-skill-laravel-migration-searcher" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/devsite/claude-skill-laravel-migration-searcher"><img src="https://img.shields.io/packagist/v/devsite/claude-skill-laravel-migration-searcher" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/devsite/claude-skill-laravel-migration-searcher"><img src="https://img.shields.io/packagist/l/devsite/claude-skill-laravel-migration-searcher" alt="License"></a>
</p>

Intelligent Laravel migration indexer with Claude AI integration. Automatically analyzes and indexes all migrations for instant search and debugging.

## Why This Package?

**Problem:** You have hundreds or thousands of migrations. Finding specific migrations is time-consuming and error-prone.

**Solution:** This package automatically analyzes **all** migrations and creates searchable markdown indexes that Claude AI (or you) can query instantly.

## Features

- **Deep Analysis** - Analyzes DDL, DML, Raw SQL, Eloquent operations, loops
- **Multiple Views** - Chronological, by-type, by-table, by-operation indexes
- **Claude AI Integration** - Ships with SKILL.md template for Claude AI workflow
- **Complexity Scoring** - Each migration gets a 1-10 complexity score
- **Multiple Formats** - Markdown and JSON output formats
- **Configurable** - Support for custom migration paths and types
- **Team-Friendly** - Commit indexes to git, whole team benefits
- **Zero Dependencies** - Only Laravel required

## What It Analyzes

The package performs comprehensive static analysis of each migration file:

### DDL Operations (Structure)
- CREATE/ALTER/DROP/RENAME tables
- Column definitions with types and modifiers (nullable, default, unique, etc.)
- Indexes (index, unique, primary)
- Foreign keys with dependency tracking

### DML Operations (Data)
- INSERT/UPDATE/DELETE via `DB::table()`
- WHERE conditions - `where`, `whereIn`, `whereNotIn`, `whereNull`, `whereNotNull`, `whereBetween`, `whereHas`, `whereDoesntHave`, `orWhere`
- Columns modified in UPDATE operations
- `DB::raw` expressions (CASE WHEN, subqueries, etc.)
- Eloquent operations - `Model::create()`, `->save()`, `->delete()`
- Relationship operations - `->relation()->create()`, `->relation()->createMany()`
- Operations inside loops (foreach with save/create/delete/update)

### Raw SQL
- `DB::statement()` - complete SQL queries
- `DB::unprepared()` - full SQL code
- `DB::raw()` expressions
- Heredoc/Nowdoc SQL blocks
- Auto-detected operation type (SELECT/INSERT/UPDATE/DELETE/CREATE/ALTER/DROP/TRUNCATE)

## Installation

```bash
composer require devsite/claude-skill-laravel-migration-searcher
```

Publish configuration:

```bash
php artisan vendor:publish --tag=migration-searcher-config
```

Publish skill template (optional - auto-copied on first run):

```bash
php artisan vendor:publish --tag=migration-searcher-skill
```

## Configuration

Edit `config/migration-searcher.php`:

```php
return [
    // Where indexes will be generated (relative to project root)
    'output_path' => '.claude/skills/laravel-migration-searcher',

    // Define your migration types
    'migration_types' => [
        'default' => [
            'path' => 'database/migrations',
        ],
    ],

    // Default output format: 'markdown' or 'json'
    'default_format' => 'markdown',

    // Path to SKILL.md template
    'skill_template_path' => '...',
];
```

## Usage

### Generate Index

```bash
# Index all migrations
php artisan migrations:index

# Generate JSON format instead of markdown
php artisan migrations:index --format=json

# Refresh existing index (deletes and regenerates)
php artisan migrations:index --refresh

# Index specific type only
php artisan migrations:index --type=default

# Custom output path
php artisan migrations:index --output=/custom/path
```

### Generated Output

Default (markdown):
```
.claude/skills/laravel-migration-searcher/
├── SKILL.md               # Instructions for Claude AI
├── index-full.md          # Chronological list with full details
├── index-by-type.md       # Grouped by migration type
├── index-by-table.md      # Grouped by database table
├── index-by-operation.md  # Grouped by operation (CREATE/ALTER/DROP/DATA/RENAME)
└── stats.json             # Statistics and metadata (JSON)
```

With `--format=json`:
```
.claude/skills/laravel-migration-searcher/
├── SKILL.md               # Instructions for Claude AI
├── index-full.json        # Chronological list with full details
├── index-by-type.json     # Grouped by migration type
├── index-by-table.json    # Grouped by database table
├── index-by-operation.json # Grouped by operation
└── stats.json             # Statistics and metadata
```

### Using with Claude AI

1. Generate index:
   ```bash
   php artisan migrations:index
   ```

2. Upload to Claude:
   - Upload files from `.claude/skills/laravel-migration-searcher/` to claude.ai
   - Or use Claude Code with local file access

3. Ask Claude:
   ```
   "Find the migration that adds subscription_plan column"
   "Which migration deletes records from orders?"
   "Show all migrations with DB::raw"
   "What will break if I remove the create_users migration?"
   ```

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

### Initial Setup

```bash
composer require devsite/claude-skill-laravel-migration-searcher
php artisan vendor:publish --tag=migration-searcher-config
php artisan migrations:index
git add .claude/ config/migration-searcher.php
git commit -m "Add migration indexer"
git push
```

### Other Team Members

```bash
git pull
# Index is already in the repo - upload to Claude or use Claude Code
```

### After Adding/Modifying Migrations

```bash
php artisan migrations:index --refresh
git add .claude/
git commit -m "Refresh migration index"
```

## Index Entry Example

Each migration in the full index contains:

```markdown
### 2024_01_15_143022_add_subscription_plan_to_users.php

**Type:** default
**Path:** database/migrations/2024_01_15_143022_add_subscription_plan_to_users.php
**Timestamp:** 2024_01_15_143022
**Complexity:** 3/10

**Tables:**
- `users` (ALTER)

**Columns:**
- `subscription_plan` (string [nullable])
- `subscription_expires_at` (timestamp [nullable])

**DDL Operations:**
- **column_create:** 2 operations

**DML Operations:**
- **UPDATE** on `users`
  - WHERE: subscription_plan IS NULL
  - Columns: subscription_plan
  - Data: ['subscription_plan' => 'free']
```

## Architecture

The package follows SOLID principles with a clean separation of concerns:

```
src/
├── Console/
│   └── Commands/
│       └── IndexMigrationsCommand.php    # Constructor injection via DI
├── Contracts/                             # Interfaces (no Interface suffix)
│   ├── ContentParser.php
│   ├── FileWriter.php
│   ├── IndexDataBuilder.php              # Data preparation contract
│   ├── IndexGenerator.php
│   ├── MigrationAnalyzer.php
│   └── Renderer.php                      # Output format contract
├── DTOs/
│   ├── BaseDTO.php                       # Abstract base with Arrayable + reflection toArray()
│   └── MigrationAnalysisResult.php       # Typed immutable analysis output
├── Parsers/
│   ├── DdlParser.php                     # Columns, indexes, foreign keys, DDL ops
│   ├── DependencyParser.php              # @requires, @depends_on, FK dependencies
│   ├── DmlParser.php                     # INSERT/UPDATE/DELETE, Eloquent, loops
│   ├── FileNameParser.php                # Timestamp, name, relative path
│   ├── RawSqlParser.php                  # DB::statement, unprepared, raw, heredoc
│   └── TableDetector.php                 # Schema::create/table/drop/rename, DB::table
├── Renderers/
│   ├── JsonRenderer.php                  # Formats structured data as JSON
│   └── MarkdownRenderer.php              # Formats structured data as markdown
├── Services/
│   ├── ComplexityCalculator.php          # Pure function: calculates 1-10 score
│   ├── IndexDataBuilder.php              # Sorts, groups, calculates stats
│   ├── IndexGenerator.php                # Orchestrates data builder + renderer + writer
│   └── MigrationAnalyzer.php             # Orchestrates parsers
├── Writers/
│   └── IndexFileWriter.php              # File I/O (implements FileWriter)
└── MigrationSearcherServiceProvider.php  # Registers interface bindings
```

Data flows through a clean pipeline: raw migrations → `MigrationAnalyzer` (returns `MigrationAnalysisResult` DTO) → `toArray()` → `IndexDataBuilder` (sort, group, stats) → `Renderer` (format to markdown/JSON) → file output. Adding a new format requires only a new class implementing `Renderer`.

All contracts are bound in the service provider, making it easy to swap implementations or mock in tests.

## Requirements

- PHP 8.3+
- Laravel 11.x or 12.x

## Testing

```bash
docker compose -f docker-compose.test.yml run --rm tests
```

### Code Coverage

Generate an HTML coverage report (requires PCOV, included in the Docker image):

```bash
docker compose -f docker-compose.test.yml run --rm coverage
```

The report will be available in the `./coverage/` directory. Open `coverage/index.html` in a browser to inspect line-by-line coverage.

## License

MIT License. See [LICENSE](LICENSE) for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

- Issues: [GitHub Issues](https://github.com/AdrianKuriata/claude-skill-laravel-migration-searcher/issues)
- Questions: Open a discussion on GitHub

## Author

**Adrian Kuriata** - [GitHub](https://github.com/AdrianKuriata)
