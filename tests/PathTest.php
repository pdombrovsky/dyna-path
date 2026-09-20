<?php

namespace DynaPath\Tests;

use DynaPath\Exceptions\InvalidArgumentException;
use DynaPath\Path;
use PHPUnit\Framework\TestCase;
use stdClass;

final class PathTest extends TestCase
{
    public function testCreateBuildsPathFromSegments(): void
    {
        $path = Path::create('root', 'items', 3, 'name');

        self::assertSame(['root', 'items', 3, 'name'], $path->segments);
        self::assertSame('root.items[3].name', (string) $path);
        self::assertSame(4, count($path));
        self::assertSame('name', $path->lastSegment());
    }

    public function testFromStringBuildsPathFromParsedSegments(): void
    {
        $path = Path::fromString('map."a.b"[3].c');

        self::assertSame(['map', 'a.b', 3, 'c'], $path->segments);
        self::assertSame('map.a.b[3].c', (string) $path);
    }

    public function testSearchExpressionQuotesAttributeSegments(): void
    {
        $path = Path::create('map', 'a.b', 3, 'quote"value', 'slash/value');

        self::assertSame(
            '"map"."a.b"[3]."quote\\"value"."slash/value"',
            $path->searchExpression()
        );
    }

    public function testSearchExpressionCanResetIndexes(): void
    {
        $path = Path::create('map', 'items', 7, 'nested', 11);

        self::assertSame('"map"."items"[0]."nested"[0]', $path->searchExpression(true));
    }

    public function testMarshaledSearchExpressionIncludesContainerTypes(): void
    {
        $path = Path::create('map', 'nested', 2, 'attr');

        self::assertSame(
            '"map".M."nested".L[2].M."attr"',
            $path->marshaledSearchExpression()
        );
    }

    public function testMarshaledSearchExpressionCanResetIndexes(): void
    {
        $path = Path::create('map', 'nested', 2, 'attr', 5);

        self::assertSame(
            '"map".M."nested".L[0].M."attr".L[0]',
            $path->marshaledSearchExpression(true)
        );
    }

    public function testParentReturnsNullForRootPath(): void
    {
        self::assertNull(Path::create('root')->parent());
    }

    public function testParentReturnsParentPath(): void
    {
        $parent = Path::create('root', 'items', 2)->parent();

        self::assertNotNull($parent);
        self::assertSame(['root', 'items'], $parent->segments);
    }

    public function testChildAppendsSegmentsAndNormalizesArrayKeys(): void
    {
        $path = Path::create('root')->child([
            10 => 'items',
            20 => 3,
            30 => 'name',
        ]);

        self::assertSame(['root', 'items', 3, 'name'], $path->segments);
    }

    public function testIsParentOfRequiresStrictPrefix(): void
    {
        $parent = Path::create('root', 'items');

        self::assertTrue($parent->isParentOf(Path::create('root', 'items', 0)));
        self::assertFalse($parent->isParentOf(Path::create('root', 'items')));
        self::assertFalse($parent->isParentOf(Path::create('root', 'other', 0)));
        self::assertFalse(Path::create('root', 'items', 0)->isParentOf($parent));
    }

    public function testRelativePathOfReturnsRelativePath(): void
    {
        $base = Path::create('root', 'items');
        $relative = $base->relativePathOf(Path::create('root', 'items', 3, 'name'));

        self::assertInstanceOf(Path::class, $relative);
        self::assertSame([3, 'name'], $relative->segments);
        self::assertSame('[3].name', (string) $relative);
        self::assertSame('[3].M."name"', $relative->marshaledSearchExpression());
    }

    public function testRelativePathOfReturnsNullForEqualPaths(): void
    {
        $path = Path::create('root', 'items');

        self::assertNull($path->relativePathOf(Path::create('root', 'items')));
    }

    public function testRelativePathOfReturnsFalseForUnrelatedPath(): void
    {
        $path = Path::create('root', 'items');

        self::assertFalse($path->relativePathOf(Path::create('root', 'other', 1)));
        self::assertFalse($path->relativePathOf(Path::create('root')));
    }

    public function testCreateRejectsEmptyAttribute(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Wrong path segment found after: ''. Path segment can not be empty string.");

        Path::create('');
    }

    public function testCreateRejectsNegativeIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Wrong path segment found after: 'root.items'. Index can not be negative, '-1' given.");

        Path::create('root', 'items', -1);
    }

    public function testCreateRejectsInvalidUtf8(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path segment must be a valid UTF-8 string.');

        Path::create("\xB1\x31");
    }

    public function testChildRejectsUnsupportedSegmentType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path segment must be string or int, stdClass given.');

        Path::create('root')->child([new stdClass()]);
    }
}
