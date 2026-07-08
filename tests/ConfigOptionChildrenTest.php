<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Timeax\ConfigKit\Schema\ConfigOption;

final class ConfigOptionChildrenTest extends TestCase
{
    public function testStaticNestedChildrenSerializeRecursively(): void
    {
        $option = new ConfigOption(
            value: 'root',
            label: 'Root',
            children: [
                new ConfigOption(
                    value: 'child-1',
                    label: 'Child 1',
                    children: [
                        new ConfigOption(value: 'leaf', label: 'Leaf'),
                    ]
                ),
            ]
        );

        $serialized = $option->jsonSerialize();

        self::assertArrayHasKey('children', $serialized);
        self::assertCount(1, $serialized['children']);
        self::assertSame('child-1', $serialized['children'][0]['id']);
        self::assertCount(1, $serialized['children'][0]['children']);
        self::assertSame('leaf', $serialized['children'][0]['children'][0]['id']);
    }

    public function testClosureChildrenResolveAndSerialize(): void
    {
        $calls = 0;
        $option = new ConfigOption(
            value: 'root',
            label: 'Root',
            children: function () use (&$calls): array {
                $calls++;
                return [new ConfigOption(value: 'child', label: 'Child')];
            }
        );

        self::assertSame(0, $calls);

        $serialized = $option->jsonSerialize();

        self::assertSame(1, $calls);
        self::assertCount(1, $serialized['children']);
        self::assertSame('child', $serialized['children'][0]['id']);
    }

    public function testInvalidChildrenClosureReturnTypeThrows(): void
    {
        $option = new ConfigOption(
            value: 'root',
            label: 'Root',
            children: static fn() => 'invalid'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('children resolver must return an array of ConfigOption');

        $option->resolveChildren();
    }

    public function testInvalidChildrenClosureItemTypeThrows(): void
    {
        $option = new ConfigOption(
            value: 'root',
            label: 'Root',
            children: static fn(): array => ['bad']
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expected Timeax\ConfigKit\Schema\ConfigOption');

        $option->resolveChildren();
    }

    public function testEmptyChildrenSerializeAsStableEmptyArray(): void
    {
        $option = new ConfigOption(value: 'solo', label: 'Solo');
        $serialized = $option->jsonSerialize();

        self::assertArrayHasKey('children', $serialized);
        self::assertSame([], $serialized['children']);
    }
}
