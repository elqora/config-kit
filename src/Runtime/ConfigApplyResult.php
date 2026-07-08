<?php declare(strict_types=1);

namespace Timeax\ConfigSchema\Runtime;

use Timeax\ConfigSchema\Contracts\ProvidesConfigSchema;
use Timeax\ConfigSchema\Support\ConfigBag;
use Timeax\ConfigSchema\Support\ConfigValidationError;

final readonly class ConfigApplyResult
{
    /**
     * @param array<string,array<int,ConfigValidationError>>|null $errors
     */
    public function __construct(
        public ConfigSchemaRecord $record,
        public ConfigBag $bag,
        public ?ProvidesConfigSchema $driver = null,
        public ?array $errors = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->errors === null || $this->errors === [];
    }
}
