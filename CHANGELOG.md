# Changelog

## 1.1.0 - 2026-11-07

### Added

- Add `Path::firstSegment(): int|string` to return the first attribute name or list index in a path.

### Fixed

- Normalize named argument keys in `Path::create()` so segments retain sequential numeric indexes. This fixes failures in methods that access segments by position, including `marshaledSearchExpression()` and `isParentOf()`.
- Reject invalid UTF-8 in unquoted attribute names directly in `PathStringParser`.

### Changed

- Remove redundant segment validation from `Path::fromString()`, which now relies on validated parser output. Quoted attribute names continue to be validated during JSON decoding.
