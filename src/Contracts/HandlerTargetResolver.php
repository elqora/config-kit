<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Contracts;

use Timeax\ConfigKit\Runtime\ResolvedHandlerTarget;

interface HandlerTargetResolver
{
    public function resolve(string $key, int|string $id): ResolvedHandlerTarget;
}
