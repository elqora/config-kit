<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Tests;

use PHPUnit\Framework\TestCase;
use Elqora\ConfigKit\Schema\ConfigField;
use Elqora\ConfigKit\Schema\ConfigGroup;
use Elqora\ConfigKit\Schema\ConfigOption;
use Elqora\ConfigKit\Schema\ConfigSchema;
use Elqora\ConfigKit\Schema\ConfigTab;
use Elqora\ConfigKit\Schema\UiConfigSchema;

final class ProfileExclusionsTest extends TestCase
{
    public function testUiSchemaFiltersFieldsGroupsTabsAndOptionsForProfile(): void
    {
        $schema = new UiConfigSchema(
            settings: [
                'credentials' => new ConfigGroup(
                    label: 'Credentials',
                    children: [
                        'public_key' => new ConfigField(
                            name: 'public_key',
                            label: 'Public Key',
                        ),
                        'secret_key' => new ConfigField(
                            name: 'secret_key',
                            label: 'Secret Key',
                            excludedFromProfiles: ['tenant-lite'],
                        ),
                    ],
                ),
                'advanced' => new ConfigGroup(
                    label: 'Advanced',
                    children: [
                        'webhook_url' => new ConfigField(
                            name: 'webhook_url',
                            label: 'Webhook URL',
                        ),
                    ],
                    excludedFromProfiles: ['tenant-lite'],
                ),
                'mode' => new ConfigField(
                    name: 'mode',
                    label: 'Mode',
                    type: 'select',
                    options: [
                        new ConfigOption(
                            value: 'basic',
                            label: 'Basic',
                            children: [
                                new ConfigOption('domestic', 'Domestic'),
                                new ConfigOption('international', 'International', excludedFromProfiles: ['/^tenant-/']),
                            ],
                        ),
                        new ConfigOption('advanced', 'Advanced', excludedFromProfiles: ['tenant-lite']),
                    ],
                ),
            ],
            tabs: [
                new ConfigTab(id: 'basic', label: 'Basic'),
                new ConfigTab(id: 'advanced', label: 'Advanced', excludedFromProfiles: ['tenant-lite']),
            ],
        );

        $filtered = $schema->forProfile('tenant-lite');
        $serialized = $filtered->jsonSerialize();

        self::assertArrayHasKey('credentials', $serialized['settings']);
        self::assertArrayNotHasKey('secret_key', $serialized['settings']['credentials']['children']);
        self::assertArrayNotHasKey('advanced', $serialized['settings']);
        self::assertSame(['basic'], array_column($serialized['tabs'], 'id'));
        self::assertSame(['basic'], array_column($serialized['settings']['mode']['options'], 'value'));
        self::assertSame(
            ['domestic'],
            array_column($serialized['settings']['mode']['options'][0]['children'], 'value'),
        );
    }

    public function testDefaultProfileRequiresLiteralDefaultExclusion(): void
    {
        $schema = new UiConfigSchema([
            'regex_hidden' => new ConfigField(
                name: 'regex_hidden',
                label: 'Regex Hidden',
                excludedFromProfiles: ['/.*/'],
            ),
            'default_hidden' => new ConfigField(
                name: 'default_hidden',
                label: 'Default Hidden',
                excludedFromProfiles: ['default'],
            ),
        ]);

        $default = $schema->forProfile('default')->jsonSerialize();
        $tenant = $schema->forProfile('tenant-a')->jsonSerialize();

        self::assertArrayHasKey('regex_hidden', $default['settings']);
        self::assertArrayNotHasKey('default_hidden', $default['settings']);
        self::assertArrayNotHasKey('regex_hidden', $tenant['settings']);
        self::assertArrayHasKey('default_hidden', $tenant['settings']);
    }

    public function testFlatConfigSchemaFiltersFieldsAndOptions(): void
    {
        $schema = new ConfigSchema([
            new ConfigField(
                name: 'mode',
                label: 'Mode',
                options: [
                    new ConfigOption('card', 'Card'),
                    new ConfigOption('bank', 'Bank', excludedFromProfiles: ['tenant-lite']),
                ],
            ),
            new ConfigField(
                name: 'secret_key',
                label: 'Secret Key',
                excludedFromProfiles: ['tenant-lite'],
            ),
        ]);

        $filtered = $schema->forProfile('tenant-lite');

        self::assertSame(['mode'], array_map(
            static fn(ConfigField $field): string => $field->name,
            $filtered->fields,
        ));
        self::assertSame(['card'], array_map(
            static fn(ConfigOption $option): string|int => $option->value,
            $filtered->fields[0]->resolveOptions(),
        ));
    }

    public function testJsonSchemaAllowsProfileExclusionProperties(): void
    {
        $schema = json_decode((string) file_get_contents(__DIR__ . '/../schema/elqora.ui-config-schema.draft-07.json'), true);

        self::assertArrayHasKey('excludedFromProfiles', $schema['definitions']['ConfigField']['properties']);
        self::assertArrayHasKey('excludedFromProfiles', $schema['definitions']['ConfigGroup']['properties']);
        self::assertArrayHasKey('excludedFromProfiles', $schema['definitions']['ConfigTab']['properties']);
        self::assertArrayHasKey('excludedFromProfiles', $schema['definitions']['ConfigFieldOption']['properties']);
    }
}
