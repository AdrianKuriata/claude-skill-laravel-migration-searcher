# Changelog

All notable changes to `claude-skill-laravel-migration-searcher` will be documented in this file.

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

### [1.3.0] - Planned
- Transaction detection (DB::transaction)
- Seeder analysis
- Rollback detection
- Race condition warnings
- Performance optimization for 5000+ migrations

### [1.4.0] - Planned
- Web UI for index browsing
- Migration dependency graph visualization

---

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
