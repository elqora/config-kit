<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Runtime;

use InvalidArgumentException;
use RuntimeException;
use Timeax\ConfigKit\Contracts\ConfigFieldValidator;
use Timeax\ConfigKit\Contracts\ConfigSchemaRepository;
use Timeax\ConfigKit\Contracts\HandlerTargetResolver;
use Timeax\ConfigKit\Contracts\ProvidesConfigSchema;
use Timeax\ConfigKit\Schema\UiConfigSchema;
use Timeax\ConfigKit\Support\ConfigBag;
use Timeax\ConfigKit\Support\ConfigValidationError;

final readonly class ConfigSchemaService
{
    private ConfigSchemaStore $store;

    public function __construct(
        private ConfigSchemaRepository $repository,
        private ?HandlerTargetResolver $handlerResolver = null,
        ?ConfigFieldValidator $validator = null,
    ) {
        $this->store = new ConfigSchemaStore($validator ?? new NoopConfigFieldValidator());
    }

    /**
     * @return array{
     *   targetId:int|string,
     *   targetType:string,
     *   profile:string,
     *   sandbox:bool,
     *   settings:array<int,array<string,mixed>>,
     *   tabs:array<int,array<string,mixed>>,
     *   config:array<string,mixed>
     * }
     */
    public function settings(
        int|string $targetId,
        string $targetType,
        UiConfigSchema $schema,
        string $profile = 'default',
        bool $sandbox = false,
    ): array {
        $record = $this->repository->getOrCreate(
            targetId: $targetId,
            targetType: $targetType,
            profile: $this->normalizeProfile($profile),
            sandbox: $sandbox,
        );

        $frontend = $this->store->toFrontendSettings($schema, $this->bagForRecord($record));

        return [
            'targetId' => $targetId,
            'targetType' => $targetType,
            'profile' => $record->profile,
            'sandbox' => $sandbox,
            ...$frontend,
            'config' => $this->recordMeta($record),
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    public function apply(
        int|string $targetId,
        string $targetType,
        array $values,
        ?UiConfigSchema $schema = null,
        string $profile = 'default',
        bool $sandbox = false,
        bool $allowClearSecrets = false,
        bool $makeDefault = false,
        ?string $handlerKey = null,
        ?ProvidesConfigSchema $driver = null,
    ): ConfigApplyResult {
        if ($handlerKey !== null || $schema === null) {
            $resolved = $this->resolveHandler($handlerKey ?? $targetType, $targetId);
            $targetId = $resolved->targetId;
            $targetType = $resolved->targetType;
            $driver ??= $resolved->driver;
            $schema ??= $this->schemaForDriver($driver);
        }

        if ($schema === null && $driver instanceof ProvidesConfigSchema) {
            $schema = $this->schemaForDriver($driver);
        }

        if (!$schema instanceof UiConfigSchema) {
            throw new RuntimeException('A UiConfigSchema is required to apply configuration values.');
        }

        $record = $this->repository->getOrCreate(
            targetId: $targetId,
            targetType: $targetType,
            profile: $this->normalizeProfile($profile),
            sandbox: $sandbox,
        );

        $candidate = $this->store->candidate(
            schema: $schema,
            current: $this->bagForRecord($record),
            values: $values,
            allowClearSecrets: $allowClearSecrets,
        );

        $errors = $this->store->validateBag($schema, $candidate->bag);

        if ($driver instanceof ProvidesConfigSchema) {
            $providerValidation = $driver->validateConfig($candidate->bag);

            if (!$providerValidation->isOk()) {
                $errors = $this->mergeErrors($errors, $providerValidation->errors());
            }
        }

        if ($errors !== []) {
            return new ConfigApplyResult(
                record: $record,
                bag: $candidate->bag,
                driver: $driver,
                errors: $errors,
            );
        }

        $record->options = $candidate->options;
        $record->secrets = $candidate->secrets;
        $record->publicConfig = $driver instanceof ProvidesConfigSchema
            ? $driver->publicConfig($candidate->bag)
            : $candidate->bag->toPublicArray();

        if ($makeDefault) {
            $this->repository->clearDefaults($record->targetId, $record->targetType, $record->sandbox);
            $record->isDefault = true;
        }

        $record->markValidated();
        $record = $this->repository->save($record);

        return new ConfigApplyResult(
            record: $record,
            bag: $candidate->bag,
            driver: $driver,
            errors: null,
        );
    }

    /**
     * @return array{
     *   targetId:int|string,
     *   targetType:string,
     *   targets:array<int,array<string,mixed>>
     * }
     */
    public function profiles(int|string $targetId, string $targetType, ?bool $sandbox = null): array
    {
        return [
            'targetId' => $targetId,
            'targetType' => $targetType,
            'targets' => array_map(
                fn(ConfigSchemaRecord $record): array => $this->profileMeta($record),
                $this->repository->list($targetId, $targetType, $sandbox),
            ),
        ];
    }

    public function createProfile(
        int|string $targetId,
        string $targetType,
        string $profile,
        bool $sandbox = false,
    ): ConfigSchemaRecord {
        $profile = $this->normalizeProfile($profile);

        if ($this->repository->find($targetId, $targetType, $profile, $sandbox) instanceof ConfigSchemaRecord) {
            throw new InvalidArgumentException('That profile already exists.');
        }

        return $this->repository->getOrCreate(
            targetId: $targetId,
            targetType: $targetType,
            profile: $profile,
            sandbox: $sandbox,
        );
    }

    public function setDefaultProfile(
        int|string $targetId,
        string $targetType,
        string $profile,
        bool $sandbox = false,
    ): ConfigSchemaRecord {
        $record = $this->repository->find(
            targetId: $targetId,
            targetType: $targetType,
            profile: $this->normalizeProfile($profile),
            sandbox: $sandbox,
        );

        if (!$record instanceof ConfigSchemaRecord) {
            throw new InvalidArgumentException('The selected profile could not be found.');
        }

        return $this->repository->setDefault($record);
    }

    private function bagForRecord(ConfigSchemaRecord $record): ConfigBag
    {
        return new ConfigBag(
            sandbox: $record->sandbox,
            options: $record->options,
            secrets: $record->secrets,
        );
    }

    private function resolveHandler(string $key, int|string $targetId): ResolvedHandlerTarget
    {
        if (!$this->handlerResolver instanceof HandlerTargetResolver) {
            throw new RuntimeException('A handler resolver is required when applying without an explicit schema.');
        }

        return $this->handlerResolver->resolve($key, $targetId);
    }

    private function schemaForDriver(ProvidesConfigSchema $driver): UiConfigSchema
    {
        $schema = $driver->uiConfigSchema();

        if ($schema instanceof UiConfigSchema) {
            return $schema;
        }

        $flat = $driver->configSchema();
        $schema = $flat?->toUiConfigSchema();

        if (!$schema instanceof UiConfigSchema) {
            throw new RuntimeException('Driver does not provide a UI config schema.');
        }

        return $schema;
    }

    /**
     * @param array<string,array<int,ConfigValidationError>> $left
     * @param array<string,array<int,ConfigValidationError>> $right
     * @return array<string,array<int,ConfigValidationError>>
     */
    private function mergeErrors(array $left, array $right): array
    {
        foreach ($right as $field => $errors) {
            foreach ($errors as $error) {
                $left[$field][] = $error;
            }
        }

        return $left;
    }

    /**
     * @return array<string,mixed>
     */
    private function recordMeta(ConfigSchemaRecord $record): array
    {
        return [
            'id' => $record->id,
            'profile' => $record->profile,
            'is_default' => $record->isDefault,
            'validated_at' => $record->validatedAt,
            'health_at' => $record->healthAt,
            'updated_at' => $record->updatedAt,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function profileMeta(ConfigSchemaRecord $record): array
    {
        return [
            'id' => $record->id,
            'profile' => $record->profile,
            'label' => $record->profile === 'default' ? 'Global' : $record->profile,
            'is_sandbox' => $record->sandbox,
            'is_default' => $record->isDefault,
            'validated_at' => $record->validatedAt,
            'health_at' => $record->healthAt,
            'updated_at' => $record->updatedAt,
            'created_at' => $record->createdAt,
        ];
    }

    private function normalizeProfile(string $profile): string
    {
        $profile = trim($profile);

        if ($profile === '') {
            throw new InvalidArgumentException('Profile name is required.');
        }

        return $profile;
    }
}
