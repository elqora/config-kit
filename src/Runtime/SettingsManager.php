<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Runtime;

use RuntimeException;
use Timeax\ConfigKit\Contracts\ConfigSchemaRepository;
use Timeax\ConfigKit\Contracts\SettingsContract;
use Timeax\ConfigKit\Contracts\SettingsTargetResolver;
use Timeax\ConfigKit\Schema\UiConfigSchema;
use Timeax\ConfigKit\Support\ConfigBag;

final readonly class SettingsManager
{
    public function __construct(
        private SettingsProviderRegistry $providers,
        private ConfigSchemaRepository $repository,
        private ConfigSchemaService $schemas,
        private SettingsTargetResolver $targetResolver = new DefaultSettingsTargetResolver(),
    ) {
    }

    public function get(string $name, ?string $profile = null, bool $sandbox = false): SettingsBag
    {
        $provider = $this->providers->get($name);
        $target = $this->targetResolver->resolve($provider);
        $record = $this->resolveRecord($target, $profile, $sandbox);

        if ($record instanceof ConfigSchemaRecord && is_array($record->publicConfig)) {
            $values = $record->publicConfig;
        } else {
            $values = $provider->publicConfig(new ConfigBag(sandbox: $sandbox));
        }

        return new SettingsBag(
            manager: $this,
            name: $provider->name(),
            profile: $profile,
            sandbox: $sandbox,
            values: $values,
        );
    }

    /**
     * @param array<string,mixed> $values
     * @param array<string,mixed> $options
     */
    public function apply(string $name, array $values, ?string $profile = null, array $options = []): SettingsBag
    {
        $provider = $this->providers->get($name);
        $target = $this->targetResolver->resolve($provider);
        $sandbox = (bool) ($options['sandbox'] ?? false);
        $profileName = $this->normalizeProfile($profile);

        $result = $this->schemas->apply(
            targetId: $target->targetId,
            targetType: $target->targetType,
            values: $values,
            schema: $this->schemaFor($provider),
            profile: $profileName,
            sandbox: $sandbox,
            allowClearSecrets: (bool) ($options['allow_clear_secrets'] ?? $options['allowClearSecrets'] ?? false),
            makeDefault: (bool) ($options['is_default'] ?? $options['make_default'] ?? $options['makeDefault'] ?? false),
            handlerKey: $target->handlerKey,
            driver: $provider,
        );

        if (!$result->ok()) {
            throw new RuntimeException('Settings validation failed.');
        }

        return $this->get(
            name: $provider->name(),
            profile: (bool) ($options['is_default'] ?? $options['make_default'] ?? $options['makeDefault'] ?? false)
                ? null
                : $profileName,
            sandbox: $sandbox,
        );
    }

    public function schema(string $name): UiConfigSchema
    {
        return $this->schemaFor($this->providers->get($name));
    }

    /**
     * @return array<string,mixed>
     */
    public function settingsPayload(
        string $name,
        ?string $profile = null,
        bool $sandbox = false,
    ): array {
        $provider = $this->providers->get($name);
        $target = $this->targetResolver->resolve($provider);

        return $this->schemas->settings(
            targetId: $target->targetId,
            targetType: $target->targetType,
            schema: $this->schemaFor($provider),
            profile: $this->normalizeProfile($profile),
            sandbox: $sandbox,
        );
    }

    private function schemaFor(SettingsContract $provider): UiConfigSchema
    {
        $schema = $provider->uiConfigSchema();

        if ($schema instanceof UiConfigSchema) {
            return $schema;
        }

        $schema = $provider->configSchema()?->toUiConfigSchema();

        if (!$schema instanceof UiConfigSchema) {
            throw new RuntimeException(sprintf('Settings provider [%s] does not provide a UI config schema.', $provider->name()));
        }

        return $schema;
    }

    private function resolveRecord(SettingsTarget $target, ?string $profile, bool $sandbox): ?ConfigSchemaRecord
    {
        if ($profile !== null && trim($profile) !== '') {
            $record = $this->repository->find($target->targetId, $target->targetType, trim($profile), $sandbox);

            if ($record instanceof ConfigSchemaRecord) {
                return $record;
            }
        }

        $record = $this->repository->findDefault($target->targetId, $target->targetType, $sandbox);

        if ($record instanceof ConfigSchemaRecord) {
            return $record;
        }

        $record = $this->repository->find($target->targetId, $target->targetType, 'default', $sandbox);

        if ($record instanceof ConfigSchemaRecord) {
            return $record;
        }

        return $this->repository->list($target->targetId, $target->targetType, $sandbox)[0] ?? null;
    }

    private function normalizeProfile(?string $profile): string
    {
        $profile = $profile !== null ? trim($profile) : '';

        return $profile !== '' ? $profile : 'default';
    }
}
