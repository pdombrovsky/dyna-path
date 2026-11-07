<?php

namespace DynaPath\Tests;

use DynaPath\Path;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final readonly class ExtendedPath extends Path
{
}

final class PathInheritanceTest extends TestCase
{
    public function testPathIsDesignedForInheritanceWithoutExposingConstructor(): void
    {
        $reflection = new ReflectionClass(Path::class);
        $constructor = $reflection->getConstructor();

        self::assertFalse($reflection->isFinal());
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
    }

    public function testCreateUsesLateStaticBinding(): void
    {
        $path = ExtendedPath::create('root', 'child');

        self::assertInstanceOf(ExtendedPath::class, $path);
        self::assertSame(['root', 'child'], $path->segments);
    }

    public function testFromStringUsesLateStaticBinding(): void
    {
        $path = ExtendedPath::fromString('root.child[2]');

        self::assertInstanceOf(ExtendedPath::class, $path);
        self::assertSame(['root', 'child', 2], $path->segments);
    }

    public function testParentPreservesConcretePathType(): void
    {
        $parent = ExtendedPath::create('root', 'child')->parent();

        self::assertInstanceOf(ExtendedPath::class, $parent);
        self::assertSame(['root'], $parent->segments);
    }

    public function testChildPreservesConcretePathType(): void
    {
        $child = ExtendedPath::create('root')->child(['child', 2]);

        self::assertInstanceOf(ExtendedPath::class, $child);
        self::assertSame(['root', 'child', 2], $child->segments);
    }

    public function testRelativePathPreservesConcreteBasePathType(): void
    {
        $base = ExtendedPath::create('root', 'items');
        $child = ExtendedPath::create('root', 'items', 2, 'name');
        $relative = $base->relativePathOf($child);

        self::assertInstanceOf(ExtendedPath::class, $relative);
        self::assertSame([2, 'name'], $relative->segments);
    }
}
