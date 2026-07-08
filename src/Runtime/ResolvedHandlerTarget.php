<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Runtime;

use Timeax\ConfigKit\Contracts\ProvidesConfigSchema;

final readonly class ResolvedHandlerTarget
{
    public function __construct(
        public string $handlerKey,
        public object $target,
        public int|string $targetId,
        public string $targetType,
        public ProvidesConfigSchema $driver,
        public HandlerDefinition $definition,
    ) {
    }
}
