<?php declare(strict_types=1);

namespace Timeax\ConfigSchema\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Timeax\ConfigSchema\Contracts\SettingsContract;
use Timeax\ConfigSchema\Contracts\SettingsTargetResolver;
use Timeax\ConfigSchema\Runtime\ConfigSchemaRecord;
use Timeax\ConfigSchema\Runtime\ConfigSchemaService;
use Timeax\ConfigSchema\Runtime\SettingsManager;
use Timeax\ConfigSchema\Runtime\SettingsProviderRegistry;
use Timeax\ConfigSchema\Runtime\SettingsTarget;
use Timeax\ConfigSchema\Schema\ConfigField;
use Timeax\ConfigSchema\Schema\ConfigSchema;
use Timeax\ConfigSchema\Schema\UiConfigSchema;
use Timeax\ConfigSchema\Support\ConfigBag;
use Timeax\ConfigSchema\Support\ConfigValidationResult;

final class RuntimeSettingsTest extends TestCase
{
    public function testRegistryResolvesProvidersAndRejectsDuplicatesOrMissingNames(): void
    {
        $provider = new RuntimeSettingsProvider('two-factor');
        $registry = new SettingsProviderRegistry([$provider]);

        self::assertSame($provider, $registry->get('two-factor'));
        self::assertSame(['two-factor' => $provider], $registry->all());

        $this->expectException(InvalidArgumentException::class);
        $registry->register(new RuntimeSettingsProvider('two-factor'));
    }

    public function testRegistryRejectsMissingProviderNames(): void
    {
        $registry = new SettingsProviderRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown settings provider [missing].');
        $registry->get('missing');
    }

    public function testSettingsBagReadsNestedValuesAndDefaults(): void
    {
        $repository = new RuntimeInMemoryRepository();
        $repository->save(new ConfigSchemaRecord(
            id: 99,
            targetId: 'two-factor',
            targetType: 'settings',
            publicConfig: [
                'otp' => ['issuer' => 'Acme'],
                'remember_device' => ['minutes' => 60],
            ],
        ));

        $manager = $this->manager($repository);
        $bag = $manager->get('two-factor');

        self::assertSame('two-factor', $bag->name());
        self::assertNull($bag->profile());
        self::assertFalse($bag->sandbox());
        self::assertSame('Acme', $bag->get('otp.issuer'));
        self::assertSame(60, $bag->get('remember_device.minutes'));
        self::assertSame('fallback', $bag->get('otp.missing', 'fallback'));
        self::assertSame($bag->all(), $bag->get());
    }

    public function testSettingsManagerFallsBackToProviderDefaultsWithoutRecord(): void
    {
        $manager = $this->manager(new RuntimeInMemoryRepository());

        $bag = $manager->get('two-factor');

        self::assertSame('Default App', $bag->get('otp.issuer'));
        self::assertSame(43200, $bag->get('remember_device.minutes'));
    }

    public function testSettingsBagApplyAndSetPersistThroughConfigSchemaService(): void
    {
        $repository = new RuntimeInMemoryRepository();
        $manager = $this->manager($repository);

        $applied = $manager->get('two-factor')->apply([
            'issuer' => 'Applied App',
            'minutes' => 120,
        ]);

        self::assertSame('Applied App', $applied->get('otp.issuer'));
        self::assertSame(120, $applied->get('remember_device.minutes'));

        $set = $applied->set('issuer', 'Set App');

        self::assertSame('Set App', $set->get('otp.issuer'));
        self::assertSame(120, $set->get('remember_device.minutes'));
    }

    public function testSettingsManagerSupportsProfilesSandboxAndCustomTargets(): void
    {
        $repository = new RuntimeInMemoryRepository();
        $resolver = new class implements SettingsTargetResolver {
            public function resolve(SettingsContract $provider): SettingsTarget
            {
                return new SettingsTarget(
                    targetId: 'site-conf:' . $provider->name(),
                    targetType: 'site-conf',
                );
            }
        };
        $manager = $this->manager($repository, $resolver);

        $applied = $manager->apply('two-factor', [
            'sandbox_label' => 'Sandbox App',
        ], profile: 'handler-a', options: ['sandbox' => true]);

        self::assertSame('handler-a', $applied->profile());
        self::assertTrue($applied->sandbox());
        self::assertSame('Sandbox App', $applied->get('sandbox.label'));

        $record = $repository->find('site-conf:two-factor', 'site-conf', 'handler-a', true);
        self::assertInstanceOf(ConfigSchemaRecord::class, $record);
        self::assertSame(['sandbox_label' => 'Sandbox App'], $record->options);

        $payload = $manager->settingsPayload('two-factor', profile: 'handler-a', sandbox: true);
        self::assertSame('site-conf:two-factor', $payload['targetId']);
        self::assertSame('site-conf', $payload['targetType']);
        self::assertSame('handler-a', $payload['profile']);
        self::assertTrue($payload['sandbox']);
    }

    public function testSettingsManagerExposesProviderSchema(): void
    {
        $manager = $this->manager(new RuntimeInMemoryRepository());

        self::assertInstanceOf(UiConfigSchema::class, $manager->schema('two-factor'));
    }

    private function manager(
        RuntimeInMemoryRepository $repository,
        ?SettingsTargetResolver $resolver = null,
    ): SettingsManager {
        return new SettingsManager(
            providers: new SettingsProviderRegistry([
                new RuntimeSettingsProvider('two-factor'),
            ]),
            repository: $repository,
            schemas: new ConfigSchemaService($repository),
            targetResolver: $resolver ?? new \Timeax\ConfigSchema\Runtime\DefaultSettingsTargetResolver(),
        );
    }
}

final class RuntimeSettingsProvider implements SettingsContract
{
    public function __construct(private string $name)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function configSchema(): ?ConfigSchema
    {
        return $this->uiConfigSchema()?->flatten();
    }

    public function uiConfigSchema(): ?UiConfigSchema
    {
        return new UiConfigSchema([
            'issuer' => new ConfigField(
                name: 'issuer',
                label: 'Issuer',
                default: 'Default App',
            ),
            'minutes' => new ConfigField(
                name: 'minutes',
                label: 'Remember Minutes',
                type: 'number',
                default: 43200,
            ),
            'sandbox_label' => new ConfigField(
                name: 'sandbox_label',
                label: 'Sandbox Label',
                default: 'Default Sandbox',
                sandbox: true,
            ),
        ]);
    }

    public function validateConfig(?ConfigBag $config = null): ConfigValidationResult
    {
        return ConfigValidationResult::ok();
    }

    public function publicConfig(?ConfigBag $config = null): array
    {
        $config ??= new ConfigBag();

        return [
            'otp' => [
                'issuer' => (string) $config->option('issuer', 'Default App'),
            ],
            'remember_device' => [
                'minutes' => (int) $config->option('minutes', 43200),
            ],
            'sandbox' => [
                'label' => (string) $config->option('sandbox_label', 'Default Sandbox'),
            ],
        ];
    }

    public function redactForLogs(mixed $payload): mixed
    {
        return $payload;
    }
}
