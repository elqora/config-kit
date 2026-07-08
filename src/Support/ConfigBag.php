<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Support;

use JsonSerializable;
use Elqora\ConfigKit\Schema\ConfigSchema;

readonly class ConfigBag implements JsonSerializable
{
    /**
     * @param array<string,mixed> $options
     * @param array<string,mixed> $secrets
     */
    public function __construct(
        public bool  $sandbox = false,
        public array $options = [],
        public array $secrets = [],
    )
    {
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function filledOption(string $key, mixed $default = null): mixed
    {
        $value = $this->option($key, $default);

        if (is_string($value) && trim($value) === '') {
            return $default;
        }

        return $value;
    }
    public function secret(string $key, mixed $default = null): mixed
    {
        return $this->secrets[$key] ?? $default;
    }

    /**
     * Filter options+secrets down to only the keys declared for this config's sandbox mode.
     * Enables storing BOTH live + sandbox values in one place (host-side),
     * then selecting the correct subset at runtime.
     */
    public function filterBySchema(ConfigSchema $schema): self
    {
        $allowed = array_flip($schema->keysForSandbox($this->sandbox));

        $options = array_intersect_key($this->options, $allowed);
        $secrets = array_intersect_key($this->secrets, $allowed);

        return new self(
            sandbox: $this->sandbox,
            options: $options,
            secrets: $secrets,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'sandbox' => $this->sandbox,
            'options' => $this->options,
            // NOTE: secrets intentionally excluded from jsonSerialize by default.
        ];
    }

    /** @return array{sandbox:bool,options:array<string,mixed>} */
    public function toPublicArray(): array
    {
        /** @var array{sandbox:bool,options:array<string,mixed>} $arr */
        $arr = $this->jsonSerialize();
        return $arr;
    }
}