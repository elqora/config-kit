<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Contracts;

use Elqora\ConfigKit\Schema\ConfigSchema;
use Elqora\ConfigKit\Schema\UiConfigSchema;
use Elqora\ConfigKit\Support\ConfigBag;
use Elqora\ConfigKit\Support\ConfigValidationResult;

interface ProvidesConfigSchema
{
    public function configSchema(): ?ConfigSchema;

    public function uiConfigSchema(): ?UiConfigSchema;

    public function validateConfig(?ConfigBag $config = null): ConfigValidationResult;

    public function publicConfig(?ConfigBag $config = null): array;

    public function redactForLogs(mixed $payload): mixed;
}