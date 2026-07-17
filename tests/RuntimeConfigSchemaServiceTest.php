<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Elqora\ConfigKit\Contracts\ConfigFieldValidator;
use Elqora\ConfigKit\Contracts\ConfigSchemaRepository;
use Elqora\ConfigKit\Contracts\ProvidesConfigSchema;
use Elqora\ConfigKit\Runtime\ConfigSchemaRecord;
use Elqora\ConfigKit\Runtime\ConfigSchemaService;
use Elqora\ConfigKit\Schema\ConfigField;
use Elqora\ConfigKit\Schema\ConfigGroup;
use Elqora\ConfigKit\Schema\ConfigOption;
use Elqora\ConfigKit\Schema\ConfigSchema;
use Elqora\ConfigKit\Schema\UiConfigSchema;
use Elqora\ConfigKit\Support\ConfigBag;
use Elqora\ConfigKit\Support\ConfigValidationResult;

final class RuntimeConfigSchemaServiceTest extends TestCase
{
    public function testSettingsPayloadUsesStoredValuesWithoutExposingSecrets(): void
    {
        $repository = new RuntimeInMemoryRepository();
        $repository->save(new ConfigSchemaRecord(
            id: 10,
            targetId: 1,
            targetType: 'gateway',
            options: ['public_key' => 'pk_live'],
            secrets: ['secret_key' => 'sk_live'],
        ));

        $service = new ConfigSchemaService($repository);
        $payload = $service->settings(1, 'gateway', self::schema());

        self::assertSame(10, $payload['config']['id']);
        self::assertSame('pk_live', $payload['settings'][0]['value']);
        self::assertSame('***', $payload['settings'][1]['value']);
    }

    public function testApplyReturnsMergedFieldAndProviderErrors(): void
    {
        $repository = new RuntimeInMemoryRepository();
        $validator = new class implements ConfigFieldValidator {
            public function validate(array $data, array $rules): array
            {
                return ['public_key' => ['Public key failed field rules.']];
            }
        };

        $driver = new RuntimeProviderDriver(providerErrors: [
            'secret_key' => ['Secret key failed provider validation.'],
        ]);
        $serviceWithDriver = new ConfigSchemaService(
            repository: $repository,
            handlerResolver: new RuntimeSingleDriverResolver($driver),
            validator: $validator,
        );

        $failed = $serviceWithDriver->apply(
            targetId: 1,
            targetType: 'gateway',
            values: ['public_key' => '', 'secret_key' => 'bad'],
            schema: null,
        );

        self::assertFalse($failed->ok());
        self::assertSame('Public key failed field rules.', $failed->errors['public_key'][0]->message);
        self::assertSame('Secret key failed provider validation.', $failed->errors['secret_key'][0]->message);
        self::assertSame([], $failed->record->options);
    }

    public function testSuccessfulApplyPersistsPublicConfigAndDefaultState(): void
    {
        $repository = new RuntimeInMemoryRepository();
        $driver = new RuntimeProviderDriver();
        $service = new ConfigSchemaService(
            repository: $repository,
            handlerResolver: new RuntimeSingleDriverResolver($driver),
        );

        $result = $service->apply(
            targetId: 7,
            targetType: 'gateway',
            values: [
                'public_key' => 'pk_new',
                'secret_key' => 'sk_new',
            ],
            makeDefault: true,
        );

        self::assertTrue($result->ok());
        self::assertSame('pk_new', $result->record->options['public_key']);
        self::assertSame('sk_new', $result->record->secrets['secret_key']);
        self::assertSame(['public_key' => 'pk_new'], $result->record->publicConfig);
        self::assertTrue($result->record->isDefault);
        self::assertNotNull($result->record->validatedAt);
        self::assertSame(2, $repository->saveCount);
    }

    public function testProfilesCanBeCreatedAndSetAsDefault(): void
    {
        $repository = new RuntimeInMemoryRepository();
        $service = new ConfigSchemaService($repository);

        $created = $service->createProfile(1, 'gateway', 'branch-a', sandbox: true);
        self::assertSame('branch-a', $created->profile);
        self::assertTrue($created->sandbox);

        $default = $service->setDefaultProfile(1, 'gateway', 'branch-a', sandbox: true);
        self::assertTrue($default->isDefault);

        $profiles = $service->profiles(1, 'gateway', sandbox: true);
        self::assertSame('branch-a', $profiles['targets'][0]['profile']);
        self::assertTrue($profiles['targets'][0]['is_default']);

        $this->expectException(InvalidArgumentException::class);
        $service->createProfile(1, 'gateway', 'branch-a', sandbox: true);
    }

    public function testProfileExcludedFieldsAreIgnoredForSettingsApplyValidationAndPublicConfig(): void
    {
        $repository = new RuntimeInMemoryRepository();
        $repository->save(new ConfigSchemaRecord(
            id: 11,
            targetId: 1,
            targetType: 'gateway',
            profile: 'limited',
            options: [
                'hidden_key' => 'old-hidden',
                'visible_key' => 'old-visible',
            ],
        ));

        $validator = new class implements ConfigFieldValidator {
            public array $data = [];
            public array $rules = [];

            public function validate(array $data, array $rules): array
            {
                $this->data = $data;
                $this->rules = $rules;

                return [];
            }
        };

        $service = new ConfigSchemaService($repository, validator: $validator);
        $schema = new UiConfigSchema([
            'hidden_key' => new ConfigField(
                name: 'hidden_key',
                label: 'Hidden Key',
                rules: ['required'],
                excludedFromProfiles: ['limited'],
            ),
            'visible_group' => new ConfigGroup(
                label: 'Visible Group',
                children: [
                    'visible_key' => new ConfigField(
                        name: 'visible_key',
                        label: 'Visible Key',
                        rules: ['required'],
                    ),
                ],
            ),
        ]);

        $payload = $service->settings(1, 'gateway', $schema, profile: 'limited');
        self::assertSame(['visible_group'], array_column($payload['settings'], 'schemaKey'));

        $result = $service->apply(
            targetId: 1,
            targetType: 'gateway',
            values: [
                'hidden_key' => 'new-hidden',
                'visible_key' => 'new-visible',
            ],
            schema: $schema,
            profile: 'limited',
        );

        self::assertTrue($result->ok());
        self::assertSame('old-hidden', $result->record->options['hidden_key']);
        self::assertSame('new-visible', $result->record->options['visible_key']);
        self::assertArrayNotHasKey('hidden_key', $validator->rules);
        self::assertArrayNotHasKey('hidden_key', $validator->data);
        self::assertSame(['visible_key' => 'new-visible'], $result->record->publicConfig['options']);
        self::assertArrayNotHasKey('hidden_key', $result->record->publicConfig['options']);
    }

    public function testRequiresAreSerializedForSettingsButOnlyEnforcedOnApply(): void
    {
        $repository = new RuntimeInMemoryRepository();
        $repository->save(new ConfigSchemaRecord(
            id: 12,
            targetId: 1,
            targetType: 'gateway',
            options: [
                'payment_method' => 'bank',
                'card_statement_descriptor' => 'old-card',
            ],
        ));

        $validator = new class implements ConfigFieldValidator {
            public array $rules = [];

            public function validate(array $data, array $rules): array
            {
                $this->rules = $rules;

                return [];
            }
        };

        $service = new ConfigSchemaService($repository, validator: $validator);
        $schema = self::requiresSchema();

        $payload = $service->settings(1, 'gateway', $schema);
        self::assertSame(
            ['payment_method', 'card_statement_descriptor'],
            array_column($payload['settings'], 'schemaKey'),
        );
        self::assertSame(
            ['payment_method' => 'card'],
            $payload['settings'][1]['requires'],
        );

        $result = $service->apply(
            targetId: 1,
            targetType: 'gateway',
            values: [
                'payment_method' => 'bank',
                'card_statement_descriptor' => 'new-card',
            ],
            schema: $schema,
        );

        self::assertTrue($result->ok());
        self::assertSame('bank', $result->record->options['payment_method']);
        self::assertSame('old-card', $result->record->options['card_statement_descriptor']);
        self::assertArrayNotHasKey('card_statement_descriptor', $validator->rules);
        self::assertSame(['payment_method' => 'bank'], $result->record->publicConfig['options']);

        $result = $service->apply(
            targetId: 1,
            targetType: 'gateway',
            values: [
                'payment_method' => 'card',
                'card_statement_descriptor' => 'new-card',
            ],
            schema: $schema,
        );

        self::assertTrue($result->ok());
        self::assertSame('card', $result->record->options['payment_method']);
        self::assertSame('new-card', $result->record->options['card_statement_descriptor']);
        self::assertArrayHasKey('card_statement_descriptor', $validator->rules);
        self::assertSame('new-card', $result->record->publicConfig['options']['card_statement_descriptor']);
    }

    public function testRequiresFilterGroupsAndOptionsForApplyContract(): void
    {
        $repository = new RuntimeInMemoryRepository();
        $repository->save(new ConfigSchemaRecord(
            id: 13,
            targetId: 1,
            targetType: 'gateway',
            options: [
                'mode' => 'basic',
                'advanced_key' => 'old-advanced',
            ],
        ));

        $service = new ConfigSchemaService($repository);
        $schema = new UiConfigSchema([
            'mode' => new ConfigField(
                name: 'mode',
                label: 'Mode',
                type: 'select',
                options: [
                    new ConfigOption('basic', 'Basic'),
                    new ConfigOption('advanced', 'Advanced', requires: ['tier' => ['in' => ['pro']]]),
                ],
            ),
            'advanced' => new ConfigGroup(
                label: 'Advanced',
                children: [
                    'advanced_key' => new ConfigField(
                        name: 'advanced_key',
                        label: 'Advanced Key',
                    ),
                ],
                requires: ['mode' => 'advanced'],
            ),
        ]);

        $basic = $service->apply(
            targetId: 1,
            targetType: 'gateway',
            values: [
                'mode' => 'basic',
                'advanced_key' => 'new-advanced',
            ],
            schema: $schema,
        );

        self::assertTrue($basic->ok());
        self::assertSame('old-advanced', $basic->record->options['advanced_key']);
        self::assertArrayNotHasKey('advanced_key', $basic->record->publicConfig['options']);

        $advanced = $service->apply(
            targetId: 1,
            targetType: 'gateway',
            values: [
                'mode' => 'advanced',
                'advanced_key' => 'new-advanced',
            ],
            schema: $schema,
        );

        self::assertTrue($advanced->ok());
        self::assertSame('new-advanced', $advanced->record->options['advanced_key']);
        self::assertSame('new-advanced', $advanced->record->publicConfig['options']['advanced_key']);
    }

    public static function schema(): UiConfigSchema
    {
        return new UiConfigSchema([
            'public_key' => new ConfigField(
                name: 'public_key',
                label: 'Public Key',
                rules: ['required'],
            ),
            'secret_key' => new ConfigField(
                name: 'secret_key',
                label: 'Secret Key',
                type: 'password',
                secret: true,
            ),
        ]);
    }

    private static function requiresSchema(): UiConfigSchema
    {
        return new UiConfigSchema([
            'payment_method' => new ConfigField(
                name: 'payment_method',
                label: 'Payment Method',
                type: 'select',
                options: [
                    new ConfigOption('card', 'Card'),
                    new ConfigOption('bank', 'Bank'),
                ],
            ),
            'card_statement_descriptor' => new ConfigField(
                name: 'card_statement_descriptor',
                label: 'Card Statement Descriptor',
                rules: ['required'],
                requires: ['payment_method' => 'card'],
            ),
        ]);
    }
}

final class RuntimeInMemoryRepository implements ConfigSchemaRepository
{
    /** @var array<string,ConfigSchemaRecord> */
    public array $records = [];

    public int $saveCount = 0;

    public function find(int|string $targetId, string $targetType, ?string $profile = null, ?bool $sandbox = null): ?ConfigSchemaRecord
    {
        foreach ($this->records as $record) {
            if ($record->targetId !== $targetId || $record->targetType !== $targetType) {
                continue;
            }

            if ($profile !== null && $record->profile !== $profile) {
                continue;
            }

            if ($sandbox !== null && $record->sandbox !== $sandbox) {
                continue;
            }

            return $record;
        }

        return null;
    }

    public function getOrCreate(int|string $targetId, string $targetType, string $profile = 'default', bool $sandbox = false, array $defaults = []): ConfigSchemaRecord
    {
        $existing = $this->find($targetId, $targetType, $profile, $sandbox);

        if ($existing instanceof ConfigSchemaRecord) {
            return $existing;
        }

        $record = new ConfigSchemaRecord(
            id: count($this->records) + 1,
            targetId: $targetId,
            targetType: $targetType,
            profile: $profile,
            sandbox: $sandbox,
            isDefault: (bool) ($defaults['isDefault'] ?? $defaults['is_default'] ?? $profile === 'default'),
            options: (array) ($defaults['options'] ?? []),
            secrets: (array) ($defaults['secrets'] ?? []),
        );

        $this->save($record);

        return $record;
    }

    public function findDefault(int|string $targetId, string $targetType, ?bool $sandbox = null): ?ConfigSchemaRecord
    {
        foreach ($this->list($targetId, $targetType, $sandbox) as $record) {
            if ($record->isDefault) {
                return $record;
            }
        }

        return null;
    }

    public function list(int|string $targetId, string $targetType, ?bool $sandbox = null): array
    {
        return array_values(array_filter(
            $this->records,
            static fn(ConfigSchemaRecord $record): bool =>
                $record->targetId === $targetId
                && $record->targetType === $targetType
                && ($sandbox === null || $record->sandbox === $sandbox),
        ));
    }

    public function save(ConfigSchemaRecord $record): ConfigSchemaRecord
    {
        $record->id ??= count($this->records) + 1;
        $this->records[$this->key($record)] = $record;
        $this->saveCount++;

        return $record;
    }

    public function clearDefaults(int|string $targetId, string $targetType, bool $sandbox): void
    {
        foreach ($this->list($targetId, $targetType, $sandbox) as $record) {
            $record->isDefault = false;
        }
    }

    public function setDefault(ConfigSchemaRecord $record): ConfigSchemaRecord
    {
        $this->clearDefaults($record->targetId, $record->targetType, $record->sandbox);
        $record->isDefault = true;

        return $this->save($record);
    }

    private function key(ConfigSchemaRecord $record): string
    {
        return implode('|', [(string) $record->targetId, $record->targetType, $record->profile, $record->sandbox ? '1' : '0']);
    }
}

final class RuntimeProviderDriver implements ProvidesConfigSchema
{
    /**
     * @param array<string,array<int,string>> $providerErrors
     */
    public function __construct(private array $providerErrors = [])
    {
    }

    public function configSchema(): ?ConfigSchema
    {
        return null;
    }

    public function uiConfigSchema(): ?UiConfigSchema
    {
        return RuntimeConfigSchemaServiceTest::schema();
    }

    public function validateConfig(?ConfigBag $config = null): ConfigValidationResult
    {
        return $this->providerErrors === []
            ? ConfigValidationResult::ok()
            : ConfigValidationResult::fail($this->providerErrors);
    }

    public function publicConfig(?ConfigBag $config = null): array
    {
        return [
            'public_key' => $config?->option('public_key'),
        ];
    }

    public function redactForLogs(mixed $payload): mixed
    {
        return $payload;
    }
}

final readonly class RuntimeSingleDriverResolver implements \Elqora\ConfigKit\Contracts\HandlerTargetResolver
{
    public function __construct(private RuntimeProviderDriver $driver)
    {
    }

    public function resolve(string $key, int|string $id): \Elqora\ConfigKit\Runtime\ResolvedHandlerTarget
    {
        $definition = new \Elqora\ConfigKit\Runtime\HandlerDefinition(
            key: $key,
            targetType: $key,
            loadTarget: static fn(): RuntimeTarget => new RuntimeTarget((int) $id),
            makeDriver: fn(): RuntimeProviderDriver => $this->driver,
        );

        $target = $definition->targetFor($id);

        return new \Elqora\ConfigKit\Runtime\ResolvedHandlerTarget(
            handlerKey: $key,
            target: $target,
            targetId: $definition->targetIdFor($target, $id),
            targetType: $definition->targetTypeFor($target),
            driver: $this->driver,
            definition: $definition,
        );
    }
}

final readonly class RuntimeTarget
{
    public function __construct(public int $id)
    {
    }
}
