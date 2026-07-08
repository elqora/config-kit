<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Runtime;

use Closure;
use Elqora\ConfigKit\Contracts\ProvidesConfigSchema;

final readonly class HandlerDefinition
{
    public Closure $loadTarget;

    public Closure $makeDriver;

    public ?Closure $resolveTargetId;

    public ?Closure $resolveTargetType;

    public function __construct(
        public string $key,
        public string $targetType,
        callable $loadTarget,
        callable $makeDriver,
        ?callable $resolveTargetId = null,
        ?callable $resolveTargetType = null,
    ) {
        $this->loadTarget = Closure::fromCallable($loadTarget);
        $this->makeDriver = Closure::fromCallable($makeDriver);
        $this->resolveTargetId = $resolveTargetId !== null ? Closure::fromCallable($resolveTargetId) : null;
        $this->resolveTargetType = $resolveTargetType !== null ? Closure::fromCallable($resolveTargetType) : null;
    }

    public function targetFor(int|string $id): object
    {
        $target = ($this->loadTarget)($id);

        if (!is_object($target)) {
            throw new \InvalidArgumentException(sprintf('Handler [%s] target loader must return an object.', $this->key));
        }

        return $target;
    }

    public function driverFor(object $target): ProvidesConfigSchema
    {
        $driver = ($this->makeDriver)($target);

        if (!$driver instanceof ProvidesConfigSchema) {
            throw new \InvalidArgumentException(sprintf(
                'Handler [%s] driver resolver must return an instance of %s.',
                $this->key,
                ProvidesConfigSchema::class,
            ));
        }

        return $driver;
    }

    public function targetIdFor(object $target, int|string $fallback): int|string
    {
        if ($this->resolveTargetId === null) {
            return $fallback;
        }

        $resolved = ($this->resolveTargetId)($target, $fallback);

        if (!is_int($resolved) && !is_string($resolved)) {
            throw new \InvalidArgumentException(sprintf('Handler [%s] target id resolver must return int|string.', $this->key));
        }

        return $resolved;
    }

    public function targetTypeFor(object $target): string
    {
        if ($this->resolveTargetType === null) {
            return $this->targetType;
        }

        $resolved = ($this->resolveTargetType)($target, $this->targetType);

        if (!is_string($resolved) || trim($resolved) === '') {
            throw new \InvalidArgumentException(sprintf('Handler [%s] target type resolver must return a non-empty string.', $this->key));
        }

        return $resolved;
    }
}
