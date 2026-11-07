# Changelog

## 2.0.0-alpha.1 (unreleased)

### Breaking changes

- Change `Path::child(array $segments)` to `Path::child(string|int $attribute, string|int ...$segments)`. Replace `child(['items', 0])` with `child('items', 0)` or unpack a non-empty list with `child(...$segments)`. The previous `child([])` no-op is no longer supported.
- Enforce argument types for `Path::child()` through PHP's type declarations. Incompatible arguments now raise `TypeError` instead of `DynaPath\Exceptions\InvalidArgumentException`.
- Limit path depth to 32 dereference operators. The root attribute has depth 0, so a complete path can contain up to 33 segments. Paths exceeding the limit raise `DynaPath\Exceptions\InvalidArgumentException`; `child()` checks the depth of the complete resulting path.

### Changed

- Run CI for pushes and pull requests targeting `2.x`.

## 1.1.0 - 2026-11-07

### Added

- Add `Path::firstSegment(): int|string` to return the first attribute name or list index in a path.

### Fixed

- Normalize named argument keys in `Path::create()` so segments retain sequential numeric indexes. This fixes failures in methods that access segments by position, including `marshaledSearchExpression()` and `isParentOf()`.
- Reject invalid UTF-8 in unquoted attribute names directly in `PathStringParser`.

### Changed

- Remove redundant segment validation from `Path::fromString()`, which now relies on validated parser output. Quoted attribute names continue to be validated during JSON decoding.
