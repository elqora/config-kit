<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Elqora\ConfigKit\Schema\ConfigField;
use Elqora\ConfigKit\Schema\ConfigGroup;
use Elqora\ConfigKit\Schema\ConfigOption;
use Elqora\ConfigKit\Schema\ConfigSchema;
use Elqora\ConfigKit\Schema\UiConfigSchema;

final class ConfigFieldOptionsTest extends TestCase
{
    public function testStaticArrayOptionsSerializeAsBefore(): void
    {
        $field = new ConfigField(
            name: 'mode',
            label: 'Mode',
            type: 'select',
            options: [
                new ConfigOption('card', 'Card'),
                new ConfigOption('bank', 'Bank Transfer'),
            ]
        );

        $serialized = $field->jsonSerialize();

        self::assertIsArray($serialized['options']);
        self::assertCount(2, $serialized['options']);
        self::assertSame('card', $serialized['options'][0]['value']);
        self::assertSame('bank', $serialized['options'][1]['id']);
    }

    public function testClosureOptionsSerializeAfterLazyResolution(): void
    {
        $calls = 0;
        $field = new ConfigField(
            name: 'mode',
            label: 'Mode',
            type: 'select',
            options: function () use (&$calls): array {
                $calls++;

                return [
                    new ConfigOption('card', 'Card'),
                    new ConfigOption('bank', 'Bank Transfer'),
                ];
            }
        );

        self::assertSame(0, $calls);

        $serialized = $field->jsonSerialize();

        self::assertSame(1, $calls);
        self::assertCount(2, $serialized['options']);
        self::assertSame('card', $serialized['options'][0]['id']);
        self::assertSame('bank', $serialized['options'][1]['id']);
    }

    public function testWithGroupPreservesClosureWithoutResolving(): void
    {
        $calls = 0;
        $field = new ConfigField(
            name: 'mode',
            label: 'Mode',
            type: 'select',
            options: function () use (&$calls): array {
                $calls++;
                return [new ConfigOption('card', 'Card')];
            }
        );

        $moved = $field->withGroup('gateway');

        self::assertSame(0, $calls);
        self::assertIsCallable($moved->options);
    }

    public function testInvalidClosureReturnTypeThrows(): void
    {
        $field = new ConfigField(
            name: 'mode',
            label: 'Mode',
            type: 'select',
            options: static fn() => 'not-an-array'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must return an array of ConfigOption');

        $field->resolveOptions();
    }

    public function testInvalidClosureItemTypeThrows(): void
    {
        $field = new ConfigField(
            name: 'mode',
            label: 'Mode',
            type: 'select',
            options: static fn(): array => ['bad-item']
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expected Elqora\ConfigKit\Schema\ConfigOption');

        $field->resolveOptions();
    }

    public function testSchemaFlowsDoNotPrematurelyResolveClosureOptions(): void
    {
        $calls = 0;
        $field = new ConfigField(
            name: 'mode',
            label: 'Mode',
            type: 'select',
            options: function () use (&$calls): array {
                $calls++;
                return [new ConfigOption('card', 'Card')];
            },
            group: 'gateway'
        );

        $flat = new ConfigSchema([$field]);
        $tree = $flat->toUiConfigSchema();

        self::assertInstanceOf(UiConfigSchema::class, $tree);
        self::assertSame(0, $calls);

        $ui = new UiConfigSchema([
            'gateway' => new ConfigGroup(label: 'Gateway', children: [
                'mode' => $field,
            ]),
        ]);

        $flattened = $ui->flatten();

        self::assertInstanceOf(ConfigSchema::class, $flattened);
        self::assertSame(0, $calls);
    }
}
