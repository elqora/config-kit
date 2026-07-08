<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Contracts;

use Elqora\ConfigKit\Runtime\ResolvedHandlerTarget;

interface HandlerTargetResolver
{
    public function resolve(string $key, int|string $id): ResolvedHandlerTarget;
}
