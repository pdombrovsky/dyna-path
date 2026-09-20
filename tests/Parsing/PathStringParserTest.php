<?php

namespace DynaPath\Tests\Parsing;

use DynaPath\Exceptions\InvalidArgumentException;
use DynaPath\Parsing\PathStringParser;
use PHPUnit\Framework\TestCase;
use const PHP_INT_MAX;

final class PathStringParserTest extends TestCase
{
    public function testParsesSimplePath(): void
    {
        self::assertSame(
            ['a', 'b', 3, 'with.dots', 'c'],
            PathStringParser::parse('a.b[3]."with.dots".c')
        );
    }

    public function testParsesMultipleIndexes(): void
    {
        self::assertSame(
            ['root', 'items', 0, 10, 'name'],
            PathStringParser::parse('root.items[0][10].name')
        );
    }

    public function testParsesQuotedJsonEscapes(): void
    {
        self::assertSame(
            ['root', 'a"b', 'slash\\value', "line\nfeed", 'solidus/value'],
            PathStringParser::parse('root."a\\"b"."slash\\\\value"."line\\nfeed"."solidus\\/value"')
        );
    }

    public function testParsesUnicodeEscape(): void
    {
        self::assertSame(
            ['root', '€'],
            PathStringParser::parse('root."\\u20AC"')
        );
    }

    public function testAcceptsMaximumIntegerIndex(): void
    {
        self::assertSame(
            ['root', PHP_INT_MAX],
            PathStringParser::parse('root[' . PHP_INT_MAX . ']')
        );
    }

    public function testRejectsEmptyInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input string cannot be empty.');

        PathStringParser::parse('');
    }

    public function testRejectsEmptyAttributeAtBeginning(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Empty attribute name found. Processed symbols: ''.");

        PathStringParser::parse('.root');
    }

    public function testRejectsEmptyAttributeBetweenDots(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty attribute name found.');

        PathStringParser::parse('root..child');
    }

    public function testRejectsTrailingDot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty attribute name found.');

        PathStringParser::parse('root.');
    }

    public function testRejectsIndexWithoutAttribute(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Index used without a preceding attribute name.');

        PathStringParser::parse('[0]');
    }

    public function testRejectsEmptyIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty index found.');

        PathStringParser::parse('root[]');
    }

    public function testRejectsNegativeIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Only non-negative integers are allowed in index, '-1' given.");

        PathStringParser::parse('root[-1]');
    }

    public function testRejectsNonNumericIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Only non-negative integers are allowed in index, 'abc' given.");

        PathStringParser::parse('root[abc]');
    }

    public function testRejectsLeadingZeroIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Index must not contain leading zeros, '01' given.");

        PathStringParser::parse('root[01]');
    }

    public function testRejectsIndexLargerThanPhpIntMax(): void
    {
        $tooLarge = (string) PHP_INT_MAX . '0';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Index is too large, '$tooLarge' given.");

        PathStringParser::parse("root[$tooLarge]");
    }

    public function testRejectsNestedBrackets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nested brackets are not allowed.');

        PathStringParser::parse('root[[0]]');
    }

    public function testRejectsUnmatchedClosingBracket(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unmatched closing bracket.');

        PathStringParser::parse('root]');
    }

    public function testRejectsUnmatchedOpeningBracket(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unmatched opening bracket.');

        PathStringParser::parse('root[0');
    }

    public function testRejectsDotInsideIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid character '.' inside brackets.");

        PathStringParser::parse('root[1.0]');
    }

    public function testRejectsQuotedAttributeInsideIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quoted attribute name is not allowed inside brackets.');

        PathStringParser::parse('root["index"]');
    }

    public function testRejectsUnexpectedQuoteInsideAttribute(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unexpected '\"' inside attribute name.");

        PathStringParser::parse('root"quoted"');
    }

    public function testRejectsAttributeImmediatelyAfterIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attribute name must start at beginning or after a dot.');

        PathStringParser::parse('root[0]child');
    }

    public function testRejectsAttributeImmediatelyAfterQuotedAttribute(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Attribute name must start at beginning or after a dot.');

        PathStringParser::parse('"root"child');
    }

    public function testRejectsEmptyQuotedAttribute(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quoted attribute name cannot be empty.');

        PathStringParser::parse('root.""');
    }

    public function testRejectsInvalidJsonEscapeInQuotedAttribute(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid quoted attribute name.');

        PathStringParser::parse('root."a\\q"');
    }

    public function testRejectsUnmatchedQuote(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unmatched quote.');

        PathStringParser::parse('root."quoted');
    }

    public function testRejectsUnfinishedEscapeSequence(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unfinished escape sequence.');

        PathStringParser::parse('root."quoted\\');
    }
}
