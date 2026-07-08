<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Contracts;

use Timeax\ConfigKit\Schema\ConfigSchema;
use Timeax\ConfigKit\Schema\UiConfigSchema;
use Timeax\ConfigKit\Support\ConfigBag;
use Timeax\ConfigKit\Support\ConfigValidationResult;

interface ProvidesConfigSchema
{
    public function configSchema(): ?ConfigSchema;

    public function uiConfigSchema(): ?UiConfigSchema;

    public function validateConfig(?ConfigBag $config = null): ConfigValidationResult;

    public function publicConfig(?ConfigBag $config = null): array;

    public function redactForLogs(mixed $payload): mixed;
}