# DynaPath

Shared immutable path value object and parser.

`DynaPath\Path` provides reusable path representation, parsing, validation, navigation, and formatting.

## Requirements

- PHP >= 8.2

## Installation

```bash
composer require pdombrovsky/dyna-path:^1.0@alpha
```

## Path

Create a path programmatically:

```php
use DynaPath\Path;

$path = Path::create('items', 0, 'score');

(string) $path; // items[0].score
$path->segments; // ['items', 0, 'score']
```

Create a path from a string:

```php
$path = Path::fromString('map."a.b"[3].c');

$path->segments; // ['map', 'a.b', 3, 'c']
```

Quoted path segments support JSON string escaping. Indexes must be non-negative integers without leading zeros and must fit into `PHP_INT_MAX`.

### Navigation

```php
$path = Path::create('items', 0, 'score');

$parent = $path->parent();
$child = $path->child(['history', 0]);

Path::create('items')->isParentOf($path); // true
Path::create('items')->relativePathOf($path); // [0].score
```

Equal paths are not considered a parent-child pair. `relativePathOf()` returns:

- `false` when the supplied path is not below the current path;
- `null` when both paths are equal;
- a `Path` when a non-empty relative path exists.

### Search expressions

`searchExpression()` returns a JMESPath-compatible path for native/unmarshaled data:

```php
Path::create('map', 'a.b', 3, 'c')->searchExpression();
// "map"."a.b"[3]."c"
```

`marshaledSearchExpression()` returns the corresponding path for DynamoDB AttributeValue-shaped data:

```php
Path::create('map', 'nested', 2, 'attr')->marshaledSearchExpression();
// "map".M."nested".L[2].M."attr"
```

Both methods accept `$resetIndexes = true` to replace list indexes with zero.

## Inheritance

`Path` is intentionally inheritable. Its constructor remains private so the validation and creation contract stays centralized in the base class. Factory and path-producing methods use late static binding and preserve the concrete subclass type.

```php
use DynaPath\Path;

final readonly class ExpressionPath extends Path
{
}

$path = ExpressionPath::create('items', 0, 'score');

$path instanceof ExpressionPath; // true
$path->parent() instanceof ExpressionPath; // true
$path->child(['history']) instanceof ExpressionPath; // true
```

This allows specialized subclasses to add domain-specific behavior while preserving the base path contract.

## Scope

The library focuses on path representation, parsing, validation, navigation, and search-path formatting. It does not include expression evaluation or evaluator-specific behavior.

## Development

Install dependencies:

```bash
composer update
```

Run tests:

```bash
vendor/bin/phpunit
```

Run static analysis:

```bash
vendor/bin/phpstan analyse --no-progress
```

Validate PSR-4 mappings:

```bash
composer dump-autoload --optimize --strict-psr
```

CI runs PHPUnit on PHP 8.2, 8.3, 8.4, and 8.5 and runs PHPStan at `max` while checking code against the minimum supported PHP 8.2 API surface.
