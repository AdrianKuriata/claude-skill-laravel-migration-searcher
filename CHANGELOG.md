# Changelog

All notable changes to `claude-skill-laravel-migration-searcher` will be documented in this file.

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
