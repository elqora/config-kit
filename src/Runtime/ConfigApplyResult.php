<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Runtime;

use Elqora\ConfigKit\Contracts\ProvidesConfigSchema;
use Elqora\ConfigKit\Support\ConfigBag;
use Elqora\ConfigKit\Support\ConfigValidationError;

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
