# Changelog

All notable changes to `laravel-migration-searcher` will be documented in this file.

## [2.0.0] - 2025-02-17

### Added
- Initial release as Composer package
- Configurable migration types via config file
- Deep analysis of migrations:
  - DDL operations (CREATE/ALTER/DROP tables)
  - DML operations with WHERE conditions
  - DB::raw expressions (CASE WHEN, subqueries)
  - Eloquent operations (Model::create, ->save(), ->delete())
  - Operations in loops (foreach/while)
  - Complex WHERE conditions (whereIn, whereHas, orWhere)
- Multiple index views:
  - Chronological (index-full.md)
  - By type (index-by-type.md)
  - By table (index-by-table.md)
  - By operation (index-by-operation.md)
  - Statistics (stats.json)
- Claude AI integration via SKILL.md template
- English documentation
- MIT license

### Changed
- N/A (initial release)

### Fixed
- N/A (initial release)

## Future Plans

### [2.1.0] - Planned
- Transaction detection (DB::transaction)
- Seeder analysis
- Rollback detection
- Race condition warnings
- Performance optimization for 5000+ migrations

### [2.2.0] - Planned
- Web UI for index browsing
- Export to different formats (HTML, JSON)
- Migration dependency graph visualization

---

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
