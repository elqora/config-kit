<?php declare(strict_types=1);

namespace Timeax\ConfigSchema\Contracts;

use Timeax\ConfigSchema\Runtime\ConfigSchemaRecord;

interface ConfigSchemaRepository
{
    public function find(
        int|string $targetId,
        string $targetType,
        ?string $profile = null,
        ?bool $sandbox = null,
    ): ?ConfigSchemaRecord;

    /**
     * @param array<string,mixed> $defaults
     */
    public function getOrCreate(
        int|string $targetId,
        string $targetType,
        string $profile = 'default',
        bool $sandbox = false,
        array $defaults = [],
    ): ConfigSchemaRecord;

    public function findDefault(
        int|string $targetId,
        string $targetType,
        ?bool $sandbox = null,
    ): ?ConfigSchemaRecord;

    /**
     * @return array<int,ConfigSchemaRecord>
     */
    public function list(
        int|string $targetId,
        string $targetType,
        ?bool $sandbox = null,
    ): array;

    public function save(ConfigSchemaRecord $record): ConfigSchemaRecord;

    public function clearDefaults(int|string $targetId, string $targetType, bool $sandbox): void;

    public function setDefault(ConfigSchemaRecord $record): ConfigSchemaRecord;
}
