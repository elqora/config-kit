<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Tests;

use PHPUnit\Framework\TestCase;
use Timeax\ConfigKit\Contracts\ConfigFieldValidator;
use Timeax\ConfigKit\Runtime\ConfigSchemaStore;
use Timeax\ConfigKit\Schema\ConfigField;
use Timeax\ConfigKit\Schema\ConfigGroup;
use Timeax\ConfigKit\Schema\ConfigOption;
use Timeax\ConfigKit\Schema\ConfigTab;
use Timeax\ConfigKit\Schema\UiConfigSchema;
use Timeax\ConfigKit\Support\ConfigBag;
use Timeax\ConfigKit\Support\ConfigValidationError;

final class RuntimeConfigSchemaStoreTest extends TestCase
{
    public function testBuildsFrontendPayloadAndMasksSecrets(): void
    {
        $schema = $this->schema();
        $store = new ConfigSchemaStore();
        $bag = new ConfigBag(
            sandbox: false,
            options: [
                'public_key' => 'pk_live_123',
                'mode' => 'card',
            ],
            secrets: [
                'secret_key' => 'sk_live_123',
            ],
        );

        $payload = $store->toFrontendSettings($schema, $bag);

        self::assertCount(3, $payload['settings']);
        self::assertSame('credentials', $payload['settings'][0]['schemaKey']);
        self::assertSame('group', $payload['settings'][0]['type']);
        self::assertSame('pk_live_123', $payload['settings'][0]['children']['public_key']['value']);
        self::assertSame('***', $payload['settings'][0]['children']['secret_key']['value']);
        self::assertTrue($payload['settings'][0]['children']['secret_key']['is_sensitive']);
        self::assertSame('Payment', $payload['tabs'][0]['label']);

        $mode = $payload['settings'][1];
        self::assertSame('mode', $mode['key']);
        self::assertTrue($mode['isButton']);
        self::assertSame('Card', $mode['options'][0]['label']);
        self::assertSame('Visa', $mode['options'][0]['children'][0]['label']);
        self::assertSame(['public_key'], $mode['options'][0]['includes']);
    }

    public function testCandidateAppliesValuesAndPreservesOrClearsSecrets(): void
    {
        $store = new ConfigSchemaStore();
        $current = new ConfigBag(
            sandbox: false,
            options: [
                'public_key' => 'old',
                'mode' => 'card',
                'test_only' => 'keep',
            ],
            secrets: [
                'secret_key' => 'existing-secret',
            ],
        );

        $preserved = $store->candidate($this->schema(), $current, [
            'public_key' => 'new',
            'secret_key' => '',
            'test_only' => 'ignored',
        ]);

        self::assertSame('new', $preserved->options['public_key']);
        self::assertSame('existing-secret', $preserved->secrets['secret_key']);
        self::assertSame('keep', $preserved->options['test_only']);

        $masked = $store->candidate($this->schema(), $current, [
            'secret_key' => '***',
        ]);
        self::assertSame('existing-secret', $masked->secrets['secret_key']);

        $cleared = $store->candidate($this->schema(), $current, [
            'secret_key' => '',
        ], allowClearSecrets: true);
        self::assertArrayNotHasKey('secret_key', $cleared->secrets);
    }

    public function testValidateBagDelegatesFieldRulesToValidator(): void
    {
        $validator = new class implements ConfigFieldValidator {
            public array $data = [];
            public array $rules = [];

            public function validate(array $data, array $rules): array
            {
                $this->data = $data;
                $this->rules = $rules;

                return [
                    'public_key' => ['Public key is required.'],
                    'secret_key' => [new ConfigValidationError('secret_key', 'Secret key is required.', 'required')],
                ];
            }
        };

        $store = new ConfigSchemaStore($validator);
        $errors = $store->validateBag($this->schema(), new ConfigBag(
            sandbox: false,
            options: [],
            secrets: [],
        ));

        self::assertSame(['required'], $validator->rules['public_key']);
        self::assertSame('fallback-public', $validator->data['public_key']);
        self::assertSame('fallback-secret', $validator->data['secret_key']);
        self::assertSame('Public key is required.', $errors['public_key'][0]->message);
        self::assertSame('required', $errors['secret_key'][0]->code);
    }

    private function schema(): UiConfigSchema
    {
        return new UiConfigSchema(
            settings: [
                'credentials' => new ConfigGroup(
                    label: 'Credentials',
                    tabs: ['payment'],
                    children: [
                        'public_key' => new ConfigField(
                            name: 'public_key',
                            label: 'Public Key',
                            required: true,
                            rules: ['required'],
                            default: 'fallback-public',
                            tabs: ['payment'],
                        ),
                        'secret_key' => new ConfigField(
                            name: 'secret_key',
                            label: 'Secret Key',
                            type: 'password',
                            required: true,
                            secret: true,
                            rules: ['required'],
                            default: 'fallback-secret',
                            tabs: ['payment'],
                        ),
                    ],
                ),
                'mode' => new ConfigField(
                    name: 'mode',
                    label: 'Mode',
                    type: 'select',
                    isButton: true,
                    options: [
                        new ConfigOption(
                            value: 'card',
                            label: 'card',
                            includes: ['public_key'],
                            children: [
                                new ConfigOption('visa', 'visa'),
                            ],
                        ),
                    ],
                ),
                'test_only' => new ConfigField(
                    name: 'test_only',
                    label: 'Test Only',
                    sandbox: true,
                ),
            ],
            tabs: [
                new ConfigTab(id: 'payment', label: 'Payment'),
            ],
        );
    }
}
