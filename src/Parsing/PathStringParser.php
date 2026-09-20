<?php

namespace DynaPath\Parsing;

use DynaPath\Exceptions\InvalidArgumentException;
use JsonException;
use function ctype_digit;
use function in_array;
use function is_string;
use function json_decode;
use function sprintf;
use function strcmp;
use function strlen;
use function substr;
use const JSON_THROW_ON_ERROR;
use const PHP_INT_MAX;

/**
 * @internal
 */
final class PathStringParser
{
    /**
     * @var list<string|int>
     */
    private array $segments = [];
    private string $buffer = '';
    private string $previousChar = '';
    private bool $inQuotes = false;
    private bool $inIndex = false;
    private int $index = 0;

    private function __construct(
        private readonly string $pathString,
    ) {
    }

    /**
     * @return list<string|int>
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $pathString): array
    {
        if ($pathString === '') {
            throw new InvalidArgumentException('Input string cannot be empty.');
        }

        return (new self($pathString))->parsePathString();
    }

    /**
     * @return list<string|int>
     *
     * @throws InvalidArgumentException
     */
    private function parsePathString(): array
    {
        $length = strlen($this->pathString);

        while ($this->index < $length) {
            $char = $this->pathString[$this->index];

            if ($this->inQuotes) {
                $this->parseQuotedCharacter($char);

                continue;
            }
            $this->parseCharacter($char);
            $this->previousChar = $char;
            $this->index++;
        }

        $this->finishParsing();

        return $this->segments;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function parseQuotedCharacter(string $char): void
    {
        if ($char === '\\') {
            $next = $this->pathString[$this->index + 1] ?? null;
            if ($next === null) {
                $this->fail('Unfinished escape sequence.', $this->index + 1);
            }

            $this->buffer .= "\\$next";
            $this->index += 2;

            return;
        }

        if ($char === '"') {
            $this->buffer = $this->decodeQuotedAttribute();
            $this->inQuotes = false;
            $this->previousChar = '"';
            $this->index++;

            return;
        }
        $this->buffer .= $char;
        $this->index++;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function parseCharacter(string $char): void
    {
        switch ($char) {
            case '.':
                $this->handleDot();
                break;

            case '"':
                $this->handleQuote();
                break;

            case '[':
                $this->handleOpeningBracket();
                break;

            case ']':
                $this->handleClosingBracket();
                break;

            default:
                if (in_array($this->previousChar, [']', '"'], true)) {
                    $this->fail('Attribute name must start at beginning or after a dot.', $this->index);
                }

                $this->buffer .= $char;
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function handleDot(): void
    {
        if ($this->inIndex) {
            $this->fail("Invalid character '.' inside brackets.", $this->index);
        }

        if ($this->buffer === '' && ! in_array($this->previousChar, [']', '"'], true)) {
            $this->fail('Empty attribute name found.', $this->index);
        }

        $this->flushBuffer();
    }

    /**
     * @throws InvalidArgumentException
     */
    private function handleQuote(): void
    {
        if ($this->inIndex) {
            $this->fail('Quoted attribute name is not allowed inside brackets.', $this->index);
        }

        if ($this->buffer !== '') {
            $this->fail("Unexpected '\"' inside attribute name.", $this->index);
        }
        if (! in_array($this->previousChar, ['', '.'], true)) {
            $this->fail('Quoted attribute must start at beginning or after a dot.', $this->index);
        }

        $this->inQuotes = true;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function handleOpeningBracket(): void
    {
        if ($this->inIndex) {
            $this->fail('Nested brackets are not allowed.', $this->index);
        }
        if (in_array($this->previousChar, ['', '.'], true)) {
            $this->fail('Index used without a preceding attribute name.', $this->index);
        }

        $this->flushBuffer();
        $this->inIndex = true;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function handleClosingBracket(): void
    {
        if (! $this->inIndex) {
            $this->fail('Unmatched closing bracket.', $this->index);
        }
        if ($this->buffer === '') {
            $this->fail('Empty index found.', $this->index);
        }

        if (! ctype_digit($this->buffer)) {
            $this->fail("Only non-negative integers are allowed in index, '$this->buffer' given.", $this->index);
        }

        if (strlen($this->buffer) > 1 && $this->buffer[0] === '0') {
            $this->fail("Index must not contain leading zeros, '$this->buffer' given.", $this->index);
        }

        $maxInt = (string) PHP_INT_MAX;
        if (
            strlen($this->buffer) > strlen($maxInt)
            || (
                strlen($this->buffer) === strlen($maxInt)
                && strcmp($this->buffer, $maxInt) > 0
            )
        ) {
            $this->fail("Index is too large, '$this->buffer' given.", $this->index);
        }

        $this->segments[] = (int) $this->buffer;
        $this->buffer = '';
        $this->inIndex = false;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function decodeQuotedAttribute(): string
    {
        if ($this->buffer === '') {
            $this->fail('Quoted attribute name cannot be empty.', $this->index + 1);
        }
        try {
            $decoded = json_decode(
                "\"$this->buffer\"",
                false,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid quoted attribute name. %s %s',
                    $exception->getMessage(),
                    $this->processedSymbolsMessage($this->index + 1)
                ),
                previous: $exception
            );
        }
        if (! is_string($decoded)) {
            $this->fail('Quoted attribute name must decode to string.', $this->index + 1);
        }

        if ($decoded === '') {
            $this->fail('Quoted attribute name cannot be empty.', $this->index + 1);
        }

        return $decoded;
    }

    private function flushBuffer(): void
    {
        if ($this->buffer === '') {
            return;
        }
        $this->segments[] = $this->buffer;
        $this->buffer = '';
    }

    /**
     * @throws InvalidArgumentException
     */
    private function finishParsing(): void
    {
        if ($this->previousChar === '.') {
            $this->fail('Empty attribute name found.', $this->index - 1);
        }

        if ($this->inQuotes) {
            $this->fail('Unmatched quote.', $this->index);
        }
        if ($this->inIndex) {
            $this->fail('Unmatched opening bracket.', $this->index);
        }

        $this->flushBuffer();
    }

    /**
     * @throws InvalidArgumentException
     * @return never
     */
    private function fail(string $message, int $index): never
    {
        throw new InvalidArgumentException(sprintf(
            '%s %s',
            $message,
            $this->processedSymbolsMessage($index)
        ));
    }

    private function processedSymbolsMessage(int $index): string
    {
        return sprintf("Processed symbols: '%s'.", substr($this->pathString, 0, $index));
    }
}
