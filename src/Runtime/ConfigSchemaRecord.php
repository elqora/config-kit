<?php declare(strict_types=1);

namespace Timeax\ConfigSchema\Runtime;

final class ConfigSchemaRecord
{
    /**
     * @param array<string,mixed> $options
     * @param array<string,mixed> $secrets
     * @param array<string,mixed>|null $publicConfig
     * @param array<string,mixed>|null $healthPayload
     */
    public function __construct(
        public int|string|null $id,
        public int|string $targetId,
        public string $targetType,
        public string $profile = 'default',
        public bool $sandbox = false,
        public bool $isDefault = false,
        public array $options = [],
        public array $secrets = [],
        public ?array $publicConfig = null,
        public mixed $validatedAt = null,
        public mixed $healthAt = null,
        public ?array $healthPayload = null,
        public mixed $createdAt = null,
        public mixed $updatedAt = null,
    ) {
    }

    public function markValidated(mixed $timestamp = null): void
    {
        $this->validatedAt = $timestamp ?? new \DateTimeImmutable();
    }
}
