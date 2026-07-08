<?php declare(strict_types=1);

namespace Timeax\ConfigSchema\Contracts;

use Timeax\ConfigSchema\Runtime\ResolvedHandlerTarget;

interface HandlerTargetResolver
{
    public function resolve(string $key, int|string $id): ResolvedHandlerTarget;
}
