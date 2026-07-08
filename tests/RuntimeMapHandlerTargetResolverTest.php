<?php declare(strict_types=1);

namespace Timeax\ConfigSchema\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Timeax\ConfigSchema\Contracts\ProvidesConfigSchema;
use Timeax\ConfigSchema\Runtime\HandlerDefinition;
use Timeax\ConfigSchema\Runtime\MapHandlerTargetResolver;
use Timeax\ConfigSchema\Schema\ConfigSchema;
use Timeax\ConfigSchema\Schema\UiConfigSchema;
use Timeax\ConfigSchema\Support\ConfigBag;
use Timeax\ConfigSchema\Support\ConfigValidationResult;

final class RuntimeMapHandlerTargetResolverTest extends TestCase
{
    public function testRuntimeHandlerClassesAreAutoloadable(): void
    {
        self::assertTrue(interface_exists(\Timeax\ConfigSchema\Contracts\HandlerTargetResolver::class));
        self::assertTrue(class_exists(HandlerDefinition::class));
        self::assertTrue(class_exists(MapHandlerTargetResolver::class));
        self::assertTrue(class_exists(\Timeax\ConfigSchema\Runtime\ResolvedHandlerTarget::class));
    }

    public function testResolvesHandlerTargetAndDriverFromMap(): void
    {
        $target = new RuntimeMapTarget(42);
        $driver = new RuntimeMapProviderDriver();

        $resolver = new MapHandlerTargetResolver([
            'gateway' => [
                'targetType' => 'payment_gateway',
                'loadTarget' => static fn(int|string $id): RuntimeMapTarget => $target,
                'makeDriver' => static fn(RuntimeMapTarget $resolved): RuntimeMapProviderDriver => $driver,
                'resolveTargetId' => static fn(RuntimeMapTarget $resolved): int => $resolved->id,
                'resolveTargetType' => static fn(): string => 'gateway:morph',
            ],
        ]);

        $resolved = $resolver->resolve('gateway', 123);

        self::assertSame('gateway', $resolved->handlerKey);
        self::assertSame($target, $resolved->target);
        self::assertSame(42, $resolved->targetId);
        self::assertSame('gateway:morph', $resolved->targetType);
        self::assertSame($driver, $resolved->driver);
    }

    public function testResolvesByUniqueTargetType(): void
    {
        $resolver = new MapHandlerTargetResolver([
            new HandlerDefinition(
                key: 'gateway',
                targetType: 'payment_gateway',
                loadTarget: static fn(): RuntimeMapTarget => new RuntimeMapTarget(7),
                makeDriver: static fn(): RuntimeMapProviderDriver => new RuntimeMapProviderDriver(),
            ),
        ]);

        $resolved = $resolver->resolve('payment_gateway', 7);

        self::assertSame('gateway', $resolved->handlerKey);
        self::assertSame('payment_gateway', $resolved->targetType);
    }

    public function testUnknownAndInvalidHandlersFailClearly(): void
    {
        $resolver = new MapHandlerTargetResolver([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown handler key [missing].');
        $resolver->resolve('missing', 1);
    }

    public function testInvalidDriverFailsClearly(): void
    {
        $resolver = new MapHandlerTargetResolver([
            'bad' => [
                'targetType' => 'bad_target',
                'loadTarget' => static fn(): RuntimeMapTarget => new RuntimeMapTarget(1),
                'makeDriver' => static fn(): object => new \stdClass(),
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('driver resolver must return an instance');
        $resolver->resolve('bad', 1);
    }
}

final readonly class RuntimeMapTarget
{
    public function __construct(public int $id)
    {
    }
}

final class RuntimeMapProviderDriver implements ProvidesConfigSchema
{
    public function configSchema(): ?ConfigSchema
    {
        return null;
    }

    public function uiConfigSchema(): ?UiConfigSchema
    {
        return new UiConfigSchema();
    }

    public function validateConfig(?ConfigBag $config = null): ConfigValidationResult
    {
        return ConfigValidationResult::ok();
    }

    public function publicConfig(?ConfigBag $config = null): array
    {
        return [];
    }

    public function redactForLogs(mixed $payload): mixed
    {
        return $payload;
    }
}
