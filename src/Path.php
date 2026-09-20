<?php

namespace DynaPath;

use Countable;
use DynaPath\Exceptions\InvalidArgumentException;
use DynaPath\Parsing\PathStringParser;
use JsonException;
use Stringable;
use function array_key_last;
use function array_slice;
use function array_values;
use function count;
use function get_debug_type;
use function implode;
use function is_int;
use function is_string;
use function json_encode;
use function sprintf;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

readonly class Path implements Stringable, Countable
{
    private const MARSHALLED_LIST_TYPE = 'L';
    private const MARSHALLED_MAP_TYPE = 'M';

    /**
     * Creates a path from segments.
     *
     * @param array<string|int> $segments
     */
    final private function __construct(public array $segments)
    {
    }

    /**
     * Creates a validated path from segments.
     * @param string $attribute
     * @param string|int ...$segments
     *
     * @throws InvalidArgumentException
     */
    public static function create(string $attribute, string|int ...$segments): static
    {
        $segments = [$attribute, ...$segments];

        self::validateSegments($segments);

        return new static($segments);
    }

    /**
     * Creates a validated path from a string path representation.
     *
     * @throws InvalidArgumentException
     */
    public static function fromString(string $pathString): static
    {
        $segments = PathStringParser::parse($pathString);

        self::validateSegments($segments);

        return new static($segments);
    }

    /**
     * @param array<mixed> $segments
     *
     * @throws InvalidArgumentException
     */
    private static function validateSegments(array $segments): void
    {
        $validationMessage = self::getSegmentsValidationMessage($segments);

        if ($validationMessage) {
            throw new InvalidArgumentException($validationMessage);
        }
    }

    /**
     * @param array<mixed> $segments
     */
    private static function getSegmentsValidationMessage(array $segments): string
    {
        $checkedSegments = [];
        $errorMessage = '';
        foreach ($segments as $segment) {
            if (! is_string($segment) && ! is_int($segment)) {
                $errorMessage = sprintf(
                    'Path segment must be string or int, %s given.',
                    get_debug_type($segment)
                );
                break;
            }

            if (is_int($segment) && $segment < 0) {
                $errorMessage = "Index can not be negative, '$segment' given.";
                break;
            }
            if ($segment === '') {
                $errorMessage = 'Path segment can not be empty string.';
                break;
            }

            if (is_string($segment) && ! self::isValidUtf8($segment)) {
                $errorMessage = 'Path segment must be a valid UTF-8 string.';
                break;
            }

            $checkedSegments[] = $segment;
        }
        if ($errorMessage) {
            $checked = $checkedSegments === []
                ? ''
                : (new self($checkedSegments))->__toString();

            return "Wrong path segment found after: '$checked'. $errorMessage";
        }

        return '';
    }

    private static function isValidUtf8(string $value): bool
    {
        try {
            json_encode($value, JSON_THROW_ON_ERROR);
            return true;
        } catch (JsonException) {
            return false;
        }
    }

    public function __toString(): string
    {
        return $this->format($this->segments);
    }

    /**
     * Returns JMESPath search expression.
     */
    public function searchExpression(bool $resetIndexes = false): string
    {
        $segments = [];
        foreach ($this->segments as $segment) {
            if (is_int($segment)) {
                $segments[] = $resetIndexes ? 0 : $segment;
            } else {
                $segments[] = self::quoteSegment($segment);
            }
        }

        return $this->format($segments);
    }

    /**
     * Returns a JMESPath search expression for the path within marshaled
     * DynamoDB data.
     *
     * The expression is relative to the contents of the root container, so its
     * DynamoDB type is not included. Nested map and list container types are
     * added before the corresponding path segments.
     *
     * When index resetting is enabled, every list index is replaced with zero.
     */
    public function marshaledSearchExpression(bool $resetIndexes = false): string
    {
        $firstSegment = $this->segments[0];
        $segments = [
            is_int($firstSegment)
                ? ($resetIndexes ? 0 : $firstSegment)
                : self::quoteSegment($firstSegment),
        ];

        for ($idx = 1, $count = count($this->segments); $idx < $count; $idx++) {
            $segment = $this->segments[$idx];

            if (is_int($segment)) {
                $index = $resetIndexes ? 0 : $segment;
                $segments[] = self::MARSHALLED_LIST_TYPE . "[$index]";

                continue;
            }
            $segments[] = self::MARSHALLED_MAP_TYPE;
            $segments[] = self::quoteSegment($segment);
        }

        return $this->format($segments);
    }

    /**
     * Returns a JMESPath quoted identifier.
     *
     * @throws InvalidArgumentException
     */
    private static function quoteSegment(string $segment): string
    {
        try {
            return json_encode(
                $segment,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Path segment cannot be encoded as a JMESPath quoted identifier.',
                previous: $exception,
            );
        }
    }

    /**
     * @param list<string|int> $convertedNodes
     */
    protected function format(array $convertedNodes): string
    {
        if (is_int($convertedNodes[0])) {
            $parts = [''];
            $lastIndex = 0;
        } else {
            $parts = [];
            $lastIndex = -1;
        }

        foreach ($convertedNodes as $segment) {
            if (is_int($segment)) {
                $parts[$lastIndex] .= "[$segment]";
            } else {
                $parts[++$lastIndex] = $segment;
            }
        }

        return implode('.', $parts);
    }

    /**
     * Returns parent path if it exists.
     */
    public function parent(): ?static
    {
        if (count($this->segments) === 1) {
            return null;
        }

        return new static(array_slice($this->segments, 0, -1));
    }

    /**
     * Returns child path for given segments.
     *
     * @param array<int|string, string|int> $segments
     *
     * @throws InvalidArgumentException
     */
    public function child(array $segments): static
    {
        self::validateSegments($segments);

        return new static([...$this->segments, ...array_values($segments)]);
    }

    /**
     * Checks whether the current path is a parent of the given path.
     *
     * Equal paths are not considered parent-child related.
     */
    public function isParentOf(self $other): bool
    {
        $parentLength = count($this->segments);

        if ($parentLength >= count($other->segments)) {
            return false;
        }
        for ($idx = 0; $idx < $parentLength; $idx++) {
            if ($this->segments[$idx] !== $other->segments[$idx]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the given child path relative to the current path.
     *
     * The current path is treated as the parent/base path.
     *
     * Return values:
     * - false when the given path is not a child of the current path;
     * - null when both paths are equal and the relative path is empty;
     * - Path when the relative path is not empty.
     */
    public function relativePathOf(self $child): false|null|static
    {
        $parentLength = count($this->segments);
        $childLength = count($child->segments);
        if ($parentLength > $childLength) {
            return false;
        }

        for ($idx = 0; $idx < $parentLength; $idx++) {
            if ($this->segments[$idx] !== $child->segments[$idx]) {
                return false;
            }
        }

        if ($parentLength === $childLength) {
            return null;
        }

        return new static(array_slice($child->segments, $parentLength));
    }

    /**
     * Returns last segment for given path.
     */
    public function lastSegment(): int|string
    {
        return $this->segments[array_key_last($this->segments)];
    }

    public function count(): int
    {
        return count($this->segments);
    }
}
