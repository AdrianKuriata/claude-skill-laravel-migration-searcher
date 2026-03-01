# Changelog

All notable changes to `claude-skill-laravel-migration-searcher` will be documented in this file.

## [3.0.0] - 2026-03-01

### Added

#### Contracts
- `ComplexityCalculator` — interface for migration complexity scoring
- `DdlParser` — typed contract for DDL parsing with column/index/FK extraction
- `DependencyParser` — contract for migration dependency parsing
- `DmlParser` — typed contract for data modification analysis
- `MarkdownMigrationFormatter` — interface for migration formatting (full, compact, DML summary)
- `RawSqlParser` — typed contract for raw SQL detection
- `TableDetector` — typed contract returning `TableInfo` array
- `ScalarValueObject` — generic interface for ValueObjects in `BaseDTO::convertValue()` (OCP)
- `TextSanitizer` — extracted sanitization from `MarkdownMigrationFormatter` (ISP)
- `IndexGeneratorFactory` — factory for `IndexGenerator` creation (DIP)
- `MigrationFileInfo` — interface for filename parsing and path resolution (DIP)

#### DTOs
- `ColumnDefinition` — type and modifiers for table columns
- `DdlOperation` — typed DDL operations with `DdlCategory` enum
- `DependencyInfo` — migration dependencies (requires, dependsOn, foreignKeys)
- `DmlOperation` — DML operations with table, model, conditions, DB::raw tracking
- `ForeignKeyDefinition` — FK column, references, onTable
- `IndexDefinition` — index type and definition
- `RawSqlStatement` — raw SQL with `RawSqlType` and `SqlOperationType` enums
- `TableInfo` — table operation with `TableOperation` enum

#### Enums
- `DdlCategory` — column_create, column_modify, index, foreign_key, other
- `DmlOperationType` — INSERT, UPDATE, DELETE, UPDATE/INSERT, LOOP
- `RawSqlType` — statement, unprepared, raw, heredoc
- `SqlOperationType` — SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, TRUNCATE, EXPRESSION, OTHER
- `TableOperation` — CREATE, ALTER, DROP, RENAME, DATA with `label()` method

#### ValueObjects
- `ComplexityScore` — validated 1–10 integer, implements `ScalarValueObject`
- `MigrationTimestamp` — validates `YYYY_MM_DD_HHMMSS` format or `'unknown'`, implements `ScalarValueObject`

#### Exceptions
- `FileSizeLimitExceededException` — thrown when migration file exceeds size limit
- `InvalidPathException` — thrown for invalid base path
- `InvalidRendererException` — thrown for invalid renderer class
- `UnsupportedFormatException` — thrown for unavailable output format

#### Other
- `IndexGeneratorFactory` service implementation
- `HtmlSanitizer` service — dedicated implementation of `TextSanitizer` contract (SRP extraction from `MarkdownMigrationFormatter`)
- `InvalidFileExtensionException` — thrown when non-`.php` file is passed to `MigrationFileInfo::getContents()` or `getFileSize()`

### Changed (BREAKING)
- Restructured `Contracts/` directory into domain subdirectories: `Parsers/`, `Renderers/`, `Services/`, `Support/`, `Writers/` — all contract namespaces changed accordingly
- All parsers (`DdlParser`, `DmlParser`, `RawSqlParser`, `TableDetector`) now implement dedicated typed contracts instead of generic `ContentParser`, and return DTO objects instead of arrays
- `MigrationAnalysisResult` — replaced scalar types with value objects: `timestamp` → `MigrationTimestamp`, `complexity` → `ComplexityScore`, `dependencies` → `DependencyInfo`
- `ComplexityCalculator` — implements contract, returns `ComplexityScore` instead of `int`
- `MigrationAnalyzer` — depends on contract interfaces instead of concrete classes, throws `FileSizeLimitExceededException`, `maxFileSize` injected via constructor instead of `config()` call (DIP); no longer uses `File` facade directly — delegates filesystem operations to `MigrationFileInfo`
- `MigrationFileInfo` contract — added `getFileSize()` and `getContents()` methods (DIP — abstracts filesystem from `MigrationAnalyzer`)
- `IndexGenerator` contract simplified: `generateAll(array $migrations): array` (removed `setMigrations()`)
- `MarkdownRenderer` — receives `TextSanitizer` as separate constructor dependency
- `MarkdownMigrationFormatter` — implements both `MarkdownMigrationFormatter` and `TextSanitizer` contracts; `escapeHtml()` renamed to `sanitize()`
- `IndexMigrationsCommand` — uses `IndexGeneratorFactory` instead of direct `IndexDataBuilder` + `FileWriter` injection
- `BaseDTO::convertValue()` — uses `ScalarValueObject` interface instead of hardcoded `ComplexityScore`/`MigrationTimestamp` checks
- `MarkdownMigrationFormatter` — no longer implements `TextSanitizer`; receives `TextSanitizer` via constructor injection
- `PathValidator` contract — removed `normalize()` method (was internal implementation detail exposed publicly)
- `DmlParser` — `extractDMLOperations()`, `extractWhereConditions()`, `extractColumnsFromArray()`, `cleanupDataPreview()` changed from `public` to `protected` (ISP)

### Changed (non-breaking)
- `RendererResolver::resolve()` validates class existence and type before instantiation (security — prevents arbitrary constructor execution from compromised config)
- `RendererResolver` constructor — filters out non-string values from formats array (security — prevents autoloader side-effects from corrupted config)
- `IndexMigrationsCommand::indexMigrationType()` validates migration paths with `PathValidator::isWithinBasePath()` (security — prevents path traversal via config)
- `InvalidPathException::invalidBasePath()` no longer exposes raw filesystem path in error message (security — information disclosure)
- `RendererResolver` — formats map injected via constructor instead of hardcoded defaults + `config()` (DIP)
- `MigrationSearcherServiceProvider` — updated bindings for `MigrationAnalyzer`, `RendererResolver`, `MigrationFileInfo`
- `MigrationFileInfo::getRelativePath()` — improved path normalization using `str_starts_with()`
- Parsers use `str_contains()` / `str_starts_with()` instead of `strpos()` for PHP 8+ idioms
- `ServiceProvider` — `TextSanitizer` binding points to `HtmlSanitizer` instead of `MarkdownMigrationFormatter`
- `FileSizeLimitExceededException` — removed file size values from error message (security — information disclosure)
- `InvalidRendererException` — removed class name from error message (security — information disclosure)
- `DmlParser` — `foreach` regex uses possessive quantifiers to prevent ReDoS
- `MarkdownRenderer::renderStats()` — sanitizes `$type`, `$table`, and `$op` values (security — prevents HTML injection in generated output)
- `IndexGenerator` — uses constructor promotion for cleaner code (code quality)
- `BaseDTO::convertValue()` — added depth guard (max 10 levels) to prevent stack overflow from deeply nested structures (defensive programming)
- `JsonRenderer::encode()` — added `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS`, `JSON_HEX_QUOT` flags to prevent XSS in JSON output (security)
- `RendererResolver` — receives `Container` via constructor injection instead of using `app()` helper (DIP)
- `MigrationFileInfo::getContents()` and `getFileSize()` — validate `.php` extension before reading file (security — prevents reading arbitrary files)
- `IndexMigrationsCommand::cleanGeneratedFiles()` — escapes glob special characters (`\`, `*`, `?`, `[`) in output path before passing to `File::glob()` (security — prevents unintended file matching)
- `IndexMigrationsCommand::copySkillTemplate()` — validates template path with `realpath()`, `is_file()`, and `is_readable()` instead of just `File::exists()` (security — prevents directory traversal and non-file access)

### Removed
- `escapeHtml()` method from `MarkdownMigrationFormatter` contract (moved to `TextSanitizer::sanitize()`)

## [2.6.0] - 2026-02-28

### Changed
- `IndexGenerator` — all constructor dependencies are now required (non-nullable), removing fallbacks to concrete implementations (DIP)
- `MigrationSearcherServiceProvider` — `FileWriter` is now explicitly injected into `IndexGenerator` binding

## [2.5.0] - 2026-02-28

### Added
- `MarkdownMigrationFormatter` class — extracted migration formatting logic from `MarkdownRenderer` (SRP)

### Changed
- `MarkdownRenderer` now composes `MarkdownMigrationFormatter` instead of handling formatting internally
- `MarkdownRenderer::renderStats()` produces Markdown output instead of JSON (LSP fix — all renderer methods now output in the renderer's own format)
- Stats file uses renderer's file extension (`stats.md` for markdown, `stats.json` for JSON) instead of hardcoded `stats.json`
- `--refresh` cleanup pattern updated from `stats.json` to `stats.*` to support all formats
- Replaced Polish text in markdown output: `"na"` → `"on"`, `"przez"` → `"via"`

### Removed
- Public helper methods from `MarkdownRenderer` (`escapeHtml`, `formatMigrationFull`, `formatMigrationCompact`, `formatDMLSummary`) — moved to `MarkdownMigrationFormatter`

## [2.4.0] - 2026-02-28

### Changed
- `FileNameParser` moved from `Parsers` to `Support\MigrationFileInfo` — the class extracts file metadata, not migration content, so it doesn't belong in `Parsers`

## [2.3.0] - 2026-02-28

### Added
- Laravel Pint with PSR-12 preset for automatic code formatting
- PHPStan level 0 for static analysis
- Docker services `pint` and `phpstan` in docker-compose.test.yml

## [2.2.0] - 2026-02-28

### Added
- `PathValidator` service with `Contracts\PathValidator` interface — extracted path security logic
- `RendererResolver` service with `Contracts\RendererResolver` interface — extensible format resolution (OCP)
- `formats` config key — user-extensible format-to-renderer mapping

### Changed
- `IndexMigrationsCommand` — injected `PathValidator`, `FileWriter`, `RendererResolver` via constructor (DIP)
- `IndexMigrationsCommand` — renderer resolution via `RendererResolver` instead of hardcoded match (OCP)
- `IndexMigrationsCommand` — `$migrationTypes` loaded in constructor
- `MigrationSearcherServiceProvider` — `Renderer` binding uses `RendererResolver`

### Fixed
- Progress bar count mismatch when non-PHP files exist in migration directory
- Null safety in `copySkillTemplate()` when `skill_template_path` config is null

## [2.1.0] - 2026-02-28

### Changed
- Simplified command output — removed emojis, execution time, file sizes, "How to use" section, and decorative messages

### Removed
- `FormatsFileSize` trait (no longer needed after output simplification)

## [2.0.0] - 2026-02-25

### Changed (BREAKING)
- Moved Parsers from `Services\Parsers` to top-level `Parsers` namespace
- Moved Renderers from `Services\Renderers` to top-level `Renderers` namespace
- Moved Writers from `Services\Writers` to top-level `Writers` namespace
- Moved IndexMigrationsCommand from `Commands` to `Console\Commands` namespace
- Removed `Interface` suffix from all contracts (e.g., `MigrationAnalyzerInterface` → `MigrationAnalyzer`)
- Restructured tests to mirror src/ directory structure

## [1.3.0] - 2026-02-25

### Added
- `BaseDTO` abstract class with automatic `toArray()` via reflection (camelCase → snake_case) implementing `Illuminate\Contracts\Support\Arrayable`
- `MigrationAnalysisResult` DTO — typed, immutable output from `MigrationAnalyzer::analyze()`

### Changed
- `MigrationAnalyzerInterface::analyze()` now returns `MigrationAnalysisResult` instead of raw array
- `ComplexityCalculator::calculate()` accepts individual array parameters instead of full result array (ISP)
- `MigrationAnalyzer::analyze()` returns `MigrationAnalysisResult` DTO
- `IndexMigrationsCommand` converts DTO to array via `toArray()` before passing to IndexGenerator

## [1.2.2] - 2026-02-25

### Changed
- Refactored `IndexMigrationsCommand::handle()` — extracted 7 focused methods: `resolveOutputPath()`, `resolveFormat()`, `prepareOutputDirectory()`, `collectMigrations()`, `generateIndexFiles()`, `displayGeneratedFiles()`, `copySkillTemplate()`
- Replaced `app(IndexDataBuilderInterface::class)` service locator with constructor injection
- Added `int` return type to `handle()` method

## [1.2.1] - 2026-02-25

### Fixed
- `isPathWithinBase()` now correctly validates deeply nested non-existent output paths (e.g., default `.claude/skills/laravel-migration-searcher` on first run)
- `isPathWithinBase()` now normalizes `..` and `.` segments before validation, blocking deep relative path traversal attacks (e.g., `a/b/../../../../tmp/evil`)

## [1.2.0] - 2026-02-24

### Changed
- Updated SKILL.md template: added mandatory index-first search constraint
- Updated SKILL.md template: added multi-type migration awareness (Migration Types section)
- Updated SKILL.md template: added Example 5 for multi-type search scenario
- Updated SKILL.md template: improved response formatting examples with multi-type output
- Updated SKILL.md template: strengthened rules and best practices for multi-type coverage

## [1.1.1] - 2026-02-23

### Fixed
- `--refresh` no longer deletes the entire output directory — only generated files (`index-*`, `stats.json`) are removed, preserving user-modified `SKILL.md` and other custom files

## [1.1.0] - 2026-02-23

### Added
- JSON output format (`--format=json`)
- `RendererInterface` contract for pluggable output formats
- `IndexDataBuilderInterface` contract for data preparation
- `IndexDataBuilder` service separating data logic from rendering
- `JsonRenderer` for JSON output
- `default_format` config option

### Changed
- `MarkdownRenderer` now implements `RendererInterface`, accepts structured data instead of raw migrations
- `IndexGenerator` depends on `RendererInterface` + `IndexDataBuilderInterface` instead of concrete `MarkdownRenderer`
- `IndexMigrationsCommand` supports `--format` option
- `ServiceProvider` registers new bindings (`IndexDataBuilderInterface`, `RendererInterface`)

## [1.0.0] - 2026-02-17

### Added
- Initial release as Composer package

## Future Plans

### Planned
- Transaction detection (DB::transaction)
- Seeder analysis
- Rollback detection
- Race condition warnings
- Performance optimization for 5000+ migrations
- Web UI for index browsing
- Migration dependency graph visualization

---

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
