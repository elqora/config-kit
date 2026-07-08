<?php declare(strict_types=1);

namespace Timeax\ConfigSchema\Runtime;

use InvalidArgumentException;
use Timeax\ConfigSchema\Contracts\HandlerTargetResolver;

final readonly class MapHandlerTargetResolver implements HandlerTargetResolver
{
    /** @var array<string,HandlerDefinition> */
    private array $definitions;

    /**
     * @param array<string,HandlerDefinition|array<string,mixed>> $definitions
     */
    public function __construct(array $definitions)
    {
        $normalized = [];

        foreach ($definitions as $key => $definition) {
            if ($definition instanceof HandlerDefinition) {
                $normalized[$definition->key] = $definition;
                continue;
            }

            if (!is_array($definition)) {
                throw new InvalidArgumentException('Handler definitions must be HandlerDefinition instances or arrays.');
            }

            $handlerKey = is_string($key) ? $key : (string) ($definition['key'] ?? '');
            $targetType = $definition['targetType'] ?? $definition['target_type'] ?? null;
            $loadTarget = $definition['loadTarget'] ?? $definition['load_target'] ?? null;
            $makeDriver = $definition['makeDriver'] ?? $definition['make_driver'] ?? $definition['driver'] ?? null;

            if ($handlerKey === '' || !is_string($targetType) || !is_callable($loadTarget) || !is_callable($makeDriver)) {
                throw new InvalidArgumentException(sprintf('Invalid handler definition for [%s].', $handlerKey ?: (string) $key));
            }

            $normalized[$handlerKey] = new HandlerDefinition(
                key: $handlerKey,
                targetType: $targetType,
                loadTarget: $loadTarget,
                makeDriver: $makeDriver,
                resolveTargetId: $definition['resolveTargetId'] ?? $definition['resolve_target_id'] ?? null,
                resolveTargetType: $definition['resolveTargetType'] ?? $definition['resolve_target_type'] ?? null,
            );
        }

        $this->definitions = $normalized;
    }

    public function resolve(string $key, int|string $id): ResolvedHandlerTarget
    {
        $definition = $this->definitions[$key] ?? $this->definitionForTargetType($key);

        if (!$definition instanceof HandlerDefinition) {
            throw new InvalidArgumentException(sprintf('Unknown handler key [%s].', $key));
        }

        $target = $definition->targetFor($id);
        $driver = $definition->driverFor($target);

        return new ResolvedHandlerTarget(
            handlerKey: $definition->key,
            target: $target,
            targetId: $definition->targetIdFor($target, $id),
            targetType: $definition->targetTypeFor($target),
            driver: $driver,
            definition: $definition,
        );
    }

    private function definitionForTargetType(string $targetType): ?HandlerDefinition
    {
        $matched = [];

        foreach ($this->definitions as $key => $definition) {
            if ($definition->targetType === $targetType) {
                $matched[$key] = $definition;
            }
        }

        if ($matched === []) {
            return null;
        }

        if (count($matched) > 1) {
            throw new InvalidArgumentException(sprintf(
                'Ambiguous handler target type [%s]. Matched handlers: [%s].',
                $targetType,
                implode(', ', array_keys($matched)),
            ));
        }

        return array_values($matched)[0];
    }
}
