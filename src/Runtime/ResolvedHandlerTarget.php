<?php declare(strict_types=1);

namespace Timeax\ConfigSchema\Runtime;

use Timeax\ConfigSchema\Contracts\ProvidesConfigSchema;

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
