# Elqora UI Config Schema

Framework-agnostic PHP primitives for describing UI configuration forms.

This package gives SDKs, plugins, admin panels, and host applications a shared
way to describe configuration fields, option lists, nested groups, tabs,
sensitive values, and validation results without coupling the schema to a
specific frontend framework.

Use it when you want a PHP package to expose a predictable configuration
contract that another system can render, validate, store, or transform.

## Installation

```bash
composer require elqora/config-kit
```

The package is published as `elqora/config-kit` and uses the
`Elqora\ConfigKit\` namespace.

## What This Package Provides

### Nested UI Schemas

Use `UiConfigSchema` when you want to describe a tree-shaped form:

- `UiConfigSchema` is the root object.
- `settings` is an associative array of named schema nodes.
- `ConfigGroup` represents a group of child nodes.
- `ConfigField` represents an individual field.
- `ConfigTab` describes optional tab metadata that a UI can use for layout.

Nested schemas are useful when your UI naturally has grouped settings, such as
credentials, webhook options, or advanced controls.

### Flat Schemas

Use `ConfigSchema` when you want a simple list of `ConfigField` objects.

Flat schemas are useful for older integrations, simple forms, or storage flows
that do not need nested layout information.

### Options

Use `ConfigOption` for select, radio, multiselect, or button-like choices.
Options can include or exclude other fields, and they can also have nested child
options.

### Config Values

Use `ConfigBag` to pass actual configuration values around. It separates public
options from sensitive secrets and intentionally excludes secrets from default
serialization.

### Validation Results

Use `ConfigValidationResult` and `ConfigValidationError` to return structured
field-level validation feedback from providers or host applications.

### Provider Contract

Use `ProvidesConfigSchema` when a provider, service, plugin, or integration
needs to expose its schema, validate config, return public config, and redact
sensitive payloads.

### Optional Runtime Layer

Use the runtime classes when you want this package to also orchestrate schema
storage, frontend payloads, named settings providers, handler targets, and
profile/sandbox flows. The runtime is still framework-agnostic: applications
provide storage, validation, encryption, and handler discovery through adapters.

## Core Concepts

The package separates schema definitions from stored values:

- Schema classes describe what a UI should render.
- `ConfigBag` carries submitted or stored values.
- Validation result classes describe whether a config is usable.
- The JSON Schema file validates serialized nested schema payloads.

Fields can be marked as secret, sandbox-only, live-only, required, tab-bound, or
controlled by option visibility rules.

## Nested UI Schema Example

```php
<?php

use Elqora\ConfigKit\Schema\ConfigField;
use Elqora\ConfigKit\Schema\ConfigGroup;
use Elqora\ConfigKit\Schema\ConfigOption;
use Elqora\ConfigKit\Schema\ConfigTab;
use Elqora\ConfigKit\Schema\UiConfigSchema;

$schema = new UiConfigSchema(
    settings: [
        'credentials' => new ConfigGroup(
            label: 'Credentials',
            tabs: ['connection'],
            children: [
                'public_key' => new ConfigField(
                    name: 'public_key',
                    label: 'Public Key',
                    type: 'text',
                    required: true,
                    helpText: 'Your publishable API key.',
                    tabs: ['connection'],
                ),
                'secret_key' => new ConfigField(
                    name: 'secret_key',
                    label: 'Secret Key',
                    type: 'password',
                    required: true,
                    secret: true,
                    helpText: 'Stored securely by the host application.',
                    tabs: ['connection'],
                ),
            ],
        ),
        'payment_method' => new ConfigField(
            name: 'payment_method',
            label: 'Payment Method',
            type: 'select',
            required: true,
            default: 'card',
            isButton: true,
            tabs: ['checkout'],
            options: [
                new ConfigOption(
                    value: 'card',
                    label: 'Card',
                    id: 'payment_card',
                    includes: ['card_statement_descriptor'],
                ),
                new ConfigOption(
                    value: 'bank_transfer',
                    label: 'Bank Transfer',
                    id: 'payment_bank_transfer',
                    includes: ['bank_account_name'],
                ),
            ],
        ),
        'card_statement_descriptor' => new ConfigField(
            name: 'card_statement_descriptor',
            label: 'Card Statement Descriptor',
            type: 'text',
            tabs: ['checkout'],
        ),
        'bank_account_name' => new ConfigField(
            name: 'bank_account_name',
            label: 'Bank Account Name',
            type: 'text',
            tabs: ['checkout'],
        ),
    ],
    tabs: [
        new ConfigTab(id: 'connection', label: 'Connection'),
        new ConfigTab(id: 'checkout', label: 'Checkout'),
    ],
);

$payload = $schema->jsonSerialize();
```

The serialized shape is designed to be easy for frontends and other services to
consume:

```json
{
  "settings": {
    "credentials": {
      "type": "group",
      "label": "Credentials",
      "required": false,
      "children": {
        "public_key": {
          "name": "public_key",
          "label": "Public Key",
          "type": "text",
          "required": true,
          "secret": false
        }
      }
    }
  },
  "tabs": [
    {
      "id": "connection",
      "label": "Connection",
      "parentId": null
    }
  ]
}
```

## Flat Schema Example

```php
<?php

use Elqora\ConfigKit\Schema\ConfigField;
use Elqora\ConfigKit\Schema\ConfigOption;
use Elqora\ConfigKit\Schema\ConfigSchema;

$schema = new ConfigSchema([
    new ConfigField(
        name: 'mode',
        label: 'Mode',
        type: 'select',
        required: true,
        options: [
            new ConfigOption('test', 'Test'),
            new ConfigOption('live', 'Live'),
        ],
    ),
    new ConfigField(
        name: 'webhook_url',
        label: 'Webhook URL',
        type: 'url',
        rules: ['nullable', 'url'],
        helpText: 'The endpoint that receives provider events.',
    ),
]);

$payload = $schema->jsonSerialize();
```

## Converting Between Nested and Flat Schemas

You can flatten a nested schema into a `ConfigSchema`:

```php
<?php

use Elqora\ConfigKit\Schema\ConfigSchema;
use Elqora\ConfigKit\Schema\UiConfigSchema;

/** @var UiConfigSchema $uiSchema */
$flatSchema = $uiSchema->flatten();

// $flatSchema is an instance of ConfigSchema.
// Fields under root groups receive a group value like "credentials".
```

You can also rebuild a nested schema from a flat schema:

```php
<?php

use Elqora\ConfigKit\Schema\ConfigSchema;
use Elqora\ConfigKit\Schema\UiConfigSchema;

/** @var ConfigSchema $flatSchema */
$uiSchema = $flatSchema->toUiConfigSchema();

// Fields with group paths are placed back under ConfigGroup nodes.
```

Use the optional sandbox argument when flattening a nested schema:

```php
<?php

$allFields = $uiSchema->flatten();
$testFields = $uiSchema->flatten(sandbox: true);
$liveFields = $uiSchema->flatten(sandbox: false);
```

## Tabs, Includes, Excludes, and Button-Like Options

Tabs and visibility hints are intentionally plain data. The package does not
decide how a UI renders them; it exposes enough structure for your frontend or
host application to make that decision.

```php
<?php

use Elqora\ConfigKit\Schema\ConfigField;
use Elqora\ConfigKit\Schema\ConfigOption;
use Elqora\ConfigKit\Schema\ConfigTab;

$tabs = [
    new ConfigTab(id: 'basic', label: 'Basic'),
    new ConfigTab(id: 'advanced', label: 'Advanced', parentId: 'basic'),
];

$field = new ConfigField(
    name: 'checkout_type',
    label: 'Checkout Type',
    type: 'radio',
    tabs: ['basic'],
    isButton: true,
    options: [
        new ConfigOption(
            value: 'hosted',
            label: 'Hosted Checkout',
            includes: ['success_url', 'cancel_url'],
            excludes: ['embedded_theme'],
        ),
        new ConfigOption(
            value: 'embedded',
            label: 'Embedded Checkout',
            includes: ['embedded_theme'],
            excludes: ['success_url', 'cancel_url'],
        ),
    ],
);
```

Common conventions:

- `tabs` lists tab IDs where a group or field belongs.
- `includes` lists related field or group keys that should be shown.
- `excludes` lists related field or group keys that should be hidden.
- `excludedFromProfiles` removes a config object from a backend profile contract.
- `requires` declares the config values a field, group, or option needs before
  it can be applied.
- `isButton` lets a renderer display select/radio options as button-like
  choices.

Profile exclusions are supported on `ConfigField`, `ConfigGroup`, `ConfigTab`,
and `ConfigOption`, including nested option children. Plain strings match exact
profile names, while regex literals such as `/^tenant-preview-/` can match a
profile family. The `default` profile is protected from broad regex rules; use
the literal `default` rule when an object must be excluded from the default
profile.

Use `requires` when an object should only be available under certain config
values. The settings payload keeps these conditions intact so frontend
interfaces can render dynamic forms, while the backend enforces them during
apply:

```php
<?php

new ConfigField(
    name: 'card_statement_descriptor',
    label: 'Card Statement Descriptor',
    requires: ['payment_method' => 'card'],
);

new ConfigGroup(
    label: 'Bank Transfer',
    requires: ['payment_method' => ['bank', 'transfer']],
    children: [
        'bank_account_name' => new ConfigField(
            name: 'bank_account_name',
            label: 'Bank Account Name',
        ),
    ],
);

new ConfigOption(
    value: 'instant_settlement',
    label: 'Instant Settlement',
    requires: ['country' => ['in' => ['ng', 'gh']]],
);
```

Supported `requires` operators are `equals`, `not`, `in`, `notIn`, `filled`,
`empty`, and `regex`. Missing dependency values fail every operator except
`empty`; scalar comparisons use normalized string values, and array values use
contains-style matching.

## Lazy Options and Nested Option Children

`ConfigField::$options` can be an array of `ConfigOption` objects or a closure
that returns an array of `ConfigOption` objects. Closures are resolved only when
options are serialized or explicitly resolved.

```php
<?php

use Elqora\ConfigKit\Schema\ConfigField;
use Elqora\ConfigKit\Schema\ConfigOption;

$field = new ConfigField(
    name: 'currency',
    label: 'Currency',
    type: 'select',
    options: static fn (): array => [
        new ConfigOption('usd', 'USD'),
        new ConfigOption('eur', 'EUR'),
        new ConfigOption('gbp', 'GBP'),
    ],
);

$options = $field->resolveOptions();
```

`ConfigOption::$children` works the same way. This is useful for nested choices:

```php
<?php

use Elqora\ConfigKit\Schema\ConfigOption;

$option = new ConfigOption(
    value: 'bank_transfer',
    label: 'Bank Transfer',
    children: [
        new ConfigOption('domestic', 'Domestic Transfer'),
        new ConfigOption('international', 'International Transfer'),
    ],
);

$children = $option->resolveChildren();
```

If an options or children resolver returns anything other than an array of
`ConfigOption` objects, the package throws an `InvalidArgumentException`.

## Sandbox and Live Configuration

Fields can be scoped to sandbox or live mode with the `sandbox` flag.

```php
<?php

use Elqora\ConfigKit\Schema\ConfigField;
use Elqora\ConfigKit\Schema\ConfigSchema;

$schema = new ConfigSchema([
    new ConfigField(
        name: 'test_secret_key',
        label: 'Test Secret Key',
        type: 'password',
        secret: true,
        sandbox: true,
    ),
    new ConfigField(
        name: 'live_secret_key',
        label: 'Live Secret Key',
        type: 'password',
        secret: true,
        sandbox: false,
    ),
]);

$testSchema = $schema->forTest();
$liveSchema = $schema->forLive();

$testKeys = $schema->keysForSandbox(true);
$liveKeys = $schema->keysForSandbox(false);
```

You can filter a `ConfigBag` to keep only the values declared for the bag's
current mode:

```php
<?php

use Elqora\ConfigKit\Support\ConfigBag;

$config = new ConfigBag(
    sandbox: true,
    options: [
        'enabled' => true,
        'test_secret_key' => 'sk_test_...',
        'live_secret_key' => 'sk_live_...',
    ],
    secrets: [
        'test_secret_key' => 'sk_test_...',
        'live_secret_key' => 'sk_live_...',
    ],
);

$filtered = $config->filterBySchema($schema);
```

## Config Values and Secrets

`ConfigBag` stores public options separately from secrets.

```php
<?php

use Elqora\ConfigKit\Support\ConfigBag;

$config = new ConfigBag(
    sandbox: true,
    options: [
        'payment_method' => 'card',
        'webhook_url' => 'https://example.com/webhooks/provider',
    ],
    secrets: [
        'secret_key' => 'sk_test_...',
    ],
);

$method = $config->option('payment_method');
$webhookUrl = $config->filledOption('webhook_url');
$secretKey = $config->secret('secret_key');

$publicPayload = $config->jsonSerialize();
$alsoPublic = $config->toPublicArray();
```

Both `jsonSerialize()` and `toPublicArray()` exclude secrets:

```json
{
  "sandbox": true,
  "options": {
    "payment_method": "card",
    "webhook_url": "https://example.com/webhooks/provider"
  }
}
```

Mark sensitive schema fields with `secret: true`, then keep their submitted
values in `ConfigBag::$secrets`.

## Validation Results

Use `ConfigValidationResult::ok()` for valid config and
`ConfigValidationResult::fail([...])` for invalid config.

```php
<?php

use Elqora\ConfigKit\Support\ConfigBag;
use Elqora\ConfigKit\Support\ConfigValidationResult;

function validateProviderConfig(ConfigBag $config): ConfigValidationResult
{
    $errors = [];

    if ($config->filledOption('payment_method') === null) {
        $errors['payment_method'][] = 'Choose a payment method.';
    }

    if ($config->secret('secret_key') === null) {
        $errors['secret_key'][] = 'Enter a secret key.';
    }

    if ($errors !== []) {
        return ConfigValidationResult::fail($errors)
            ->addError('credentials', 'Configuration is incomplete.', 'missing_credentials');
    }

    return ConfigValidationResult::ok();
}

$result = validateProviderConfig($config);

if (! $result->isOk()) {
    return $result->jsonSerialize();
}
```

Serialized validation errors are grouped by field:

```json
{
  "ok": false,
  "errors": {
    "secret_key": [
      {
        "field": "secret_key",
        "message": "Enter a secret key.",
        "code": null
      }
    ]
  }
}
```

## Provider Contract Example

Implement `ProvidesConfigSchema` when an integration needs to expose schema and
runtime config helpers through a consistent interface.

```php
<?php

use Elqora\ConfigKit\Contracts\ProvidesConfigSchema;
use Elqora\ConfigKit\Schema\ConfigField;
use Elqora\ConfigKit\Schema\ConfigGroup;
use Elqora\ConfigKit\Schema\ConfigSchema;
use Elqora\ConfigKit\Schema\UiConfigSchema;
use Elqora\ConfigKit\Support\ConfigBag;
use Elqora\ConfigKit\Support\ConfigValidationResult;

final class PaymentProvider implements ProvidesConfigSchema
{
    public function configSchema(): ?ConfigSchema
    {
        return $this->uiConfigSchema()?->flatten();
    }

    public function uiConfigSchema(): ?UiConfigSchema
    {
        return new UiConfigSchema([
            'credentials' => new ConfigGroup(
                label: 'Credentials',
                children: [
                    'public_key' => new ConfigField(
                        name: 'public_key',
                        label: 'Public Key',
                        required: true,
                    ),
                    'secret_key' => new ConfigField(
                        name: 'secret_key',
                        label: 'Secret Key',
                        type: 'password',
                        required: true,
                        secret: true,
                    ),
                ],
            ),
        ]);
    }

    public function validateConfig(?ConfigBag $config = null): ConfigValidationResult
    {
        if ($config === null || $config->secret('secret_key') === null) {
            return ConfigValidationResult::fail([
                'secret_key' => ['Enter a secret key.'],
            ]);
        }

        return ConfigValidationResult::ok();
    }

    public function publicConfig(?ConfigBag $config = null): array
    {
        return $config?->toPublicArray() ?? [];
    }

    public function redactForLogs(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return $payload;
        }

        if (array_key_exists('secret_key', $payload)) {
            $payload['secret_key'] = '[redacted]';
        }

        return $payload;
    }
}
```

## Optional Runtime Layer

The runtime layer is for hosts that want reusable config orchestration instead
of only schema objects.

The SDK provides:

- `ConfigSchemaService` for settings payloads, apply flows, profiles, and
  default-profile changes.
- `ConfigSchemaStore` for frontend-safe nested settings payloads and candidate
  value application.
- `ConfigSchemaRepository` as the storage boundary.
- `ConfigFieldValidator` as the field-rule validation boundary.
- `HandlerTargetResolver` and `MapHandlerTargetResolver` for handler-key to
  target/provider resolution.

The SDK does not ship a database implementation. A host application maps its own
storage records to `ConfigSchemaRecord` and handles encryption before values
enter or leave the repository adapter.

## Named Settings Providers

Named settings providers are supported through `SettingsContract`. It
extends `ProvidesConfigSchema` and adds a stable settings name.

```php
<?php

use Elqora\ConfigKit\Contracts\SettingsContract;
use Elqora\ConfigKit\Schema\ConfigField;
use Elqora\ConfigKit\Schema\ConfigSchema;
use Elqora\ConfigKit\Schema\UiConfigSchema;
use Elqora\ConfigKit\Support\ConfigBag;
use Elqora\ConfigKit\Support\ConfigValidationResult;

final class TwoFactorSettings implements SettingsContract
{
    public function name(): string
    {
        return 'two-factor';
    }

    public function uiConfigSchema(): ?UiConfigSchema
    {
        return new UiConfigSchema([
            'issuer' => new ConfigField(
                name: 'issuer',
                label: 'OTP Issuer',
                default: 'My App',
            ),
            'remember_minutes' => new ConfigField(
                name: 'remember_minutes',
                label: 'Remember Minutes',
                type: 'number',
                default: 43200,
            ),
        ]);
    }

    public function configSchema(): ?ConfigSchema
    {
        return $this->uiConfigSchema()?->flatten();
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
                'issuer' => (string) $config->option('issuer', 'My App'),
            ],
            'remember_device' => [
                'minutes' => (int) $config->option('remember_minutes', 43200),
            ],
        ];
    }

    public function redactForLogs(mixed $payload): mixed
    {
        return $payload;
    }
}
```

Register providers explicitly:

```php
<?php

use Elqora\ConfigKit\Runtime\ConfigSchemaService;
use Elqora\ConfigKit\Runtime\SettingsManager;
use Elqora\ConfigKit\Runtime\SettingsProviderRegistry;

$registry = new SettingsProviderRegistry([
    new TwoFactorSettings(),
]);

$settings = new SettingsManager(
    providers: $registry,
    repository: $repository,
    schemas: new ConfigSchemaService($repository),
);
```

Read settings with dot-path access:

```php
<?php

$twoFactor = $settings->get('two-factor');

$issuer = $twoFactor->get('otp.issuer', 'Fallback App');
$minutes = $twoFactor->get('remember_device.minutes', 43200);
$all = $twoFactor->all();
```

Apply settings through the provider schema and validation flow:

```php
<?php

$updated = $settings->get('two-factor')->apply([
    'issuer' => 'Production App',
    'remember_minutes' => 10080,
]);

$updated = $updated->set('issuer', 'Admin Portal');
```

Profiles and sandbox mode are supported:

```php
<?php

$profile = $settings->apply(
    name: 'two-factor',
    values: ['issuer' => 'Tenant A'],
    profile: 'tenant-a',
    options: ['sandbox' => false, 'is_default' => true],
);
```

By default, settings are stored under `targetType = "settings"` and
`targetId = provider name`. Hosts can override that with `SettingsTargetResolver`.

## Laravel Integration

Laravel should provide adapters, not change the SDK internals.

Bind the runtime contracts in a service provider:

```php
<?php

use App\Support\ConfigSchema\LaravelConfigFieldValidator;
use App\Support\ConfigSchema\LaravelConfigSchemaRepository;
use App\Support\ConfigSchema\LaravelSettingsTargetResolver;
use Elqora\ConfigKit\Contracts\ConfigFieldValidator;
use Elqora\ConfigKit\Contracts\ConfigSchemaRepository;
use Elqora\ConfigKit\Contracts\HandlerTargetResolver;
use Elqora\ConfigKit\Contracts\SettingsTargetResolver;
use Elqora\ConfigKit\Runtime\ConfigSchemaService;
use Elqora\ConfigKit\Runtime\MapHandlerTargetResolver;
use Elqora\ConfigKit\Runtime\SettingsManager;
use Elqora\ConfigKit\Runtime\SettingsProviderRegistry;

$this->app->bind(ConfigSchemaRepository::class, LaravelConfigSchemaRepository::class);
$this->app->bind(ConfigFieldValidator::class, LaravelConfigFieldValidator::class);
$this->app->bind(SettingsTargetResolver::class, LaravelSettingsTargetResolver::class);

$this->app->bind(HandlerTargetResolver::class, function () {
    return new MapHandlerTargetResolver(config('config-schema.handlers', []));
});

$this->app->bind(ConfigSchemaService::class, function ($app) {
    return new ConfigSchemaService(
        repository: $app->make(ConfigSchemaRepository::class),
        handlerResolver: $app->make(HandlerTargetResolver::class),
        validator: $app->make(ConfigFieldValidator::class),
    );
});

$this->app->bind(SettingsManager::class, function ($app) {
    return new SettingsManager(
        providers: new SettingsProviderRegistry([
            $app->make(\App\Settings\Security\TwoFactorSettings::class),
        ]),
        repository: $app->make(ConfigSchemaRepository::class),
        schemas: $app->make(ConfigSchemaService::class),
        targetResolver: $app->make(SettingsTargetResolver::class),
    );
});
```

A Laravel repository adapter should:

- Read/write your `config_schemas` table.
- Encrypt/decrypt secrets before mapping to `ConfigSchemaRecord`.
- Map timestamps and health payloads if the host uses them.
- Implement default-profile behavior with normal database queries.

A Laravel field validator adapter should wrap `Validator::make($data, $rules)`
and return `ConfigValidationError` objects.

A settings target resolver can map provider names to application-specific
records, such as a Laravel `SiteConf` model:

```php
<?php

use App\Models\SiteConf;
use Elqora\ConfigKit\Contracts\SettingsContract;
use Elqora\ConfigKit\Contracts\SettingsTargetResolver;
use Elqora\ConfigKit\Runtime\SettingsTarget;

final class LaravelSettingsTargetResolver implements SettingsTargetResolver
{
    public function resolve(SettingsContract $provider): SettingsTarget
    {
        $conf = SiteConf::query()
            ->where('name', $provider->name())
            ->latest('id')
            ->firstOrFail();

        return new SettingsTarget(
            targetId: (int) $conf->id,
            targetType: $conf->getMorphClass(),
            handlerKey: 'system',
        );
    }
}
```

Handler definitions can stay in Laravel config:

```php
<?php

use App\Models\PaymentGateway;
use Elqora\ConfigKit\Runtime\HandlerDefinition;

return [
    'handlers' => [
        'gateway' => new HandlerDefinition(
            key: 'gateway',
            targetType: PaymentGateway::class,
            loadTarget: static fn (int|string $id) => PaymentGateway::query()->findOrFail((int) $id),
            makeDriver: static fn (PaymentGateway $gateway) => \PayKit\Pay::via($gateway->id, false),
            resolveTargetId: static fn (PaymentGateway $gateway) => (int) $gateway->getKey(),
            resolveTargetType: static fn (PaymentGateway $gateway) => $gateway->getMorphClass(),
        ),
    ],
];
```

When adding runtime classes to this package, make sure the new files are
committed or otherwise included in the installed package. Passing autoload tests
locally is not enough if `src/Runtime` or new contract files are still
untracked.

## JSON Schema

The repository includes a Draft-07 JSON Schema for validating serialized nested
schema payloads:

```text
schema/elqora.ui-config-schema.draft-07.json
```

Use it when you need to validate schema JSON before sending it to a frontend,
storing it, or accepting it from another service.

Example serialized payload:

```json
{
  "settings": {
    "credentials": {
      "type": "group",
      "label": "Credentials",
      "required": false,
      "children": {
        "public_key": {
          "name": "public_key",
          "label": "Public Key",
          "type": "text",
          "required": true,
          "secret": false,
          "rules": [],
          "default": null,
          "helpText": null,
          "options": [],
          "sandbox": false,
          "meta": {},
          "group": null,
          "tabs": [],
          "isButton": false,
          "includes": [],
          "excludes": [],
          "excludedFromProfiles": [],
          "requires": {}
        }
      },
      "meta": {},
      "tabs": [],
      "includes": [],
      "excludes": [],
      "excludedFromProfiles": [],
      "requires": {}
    }
  },
  "tabs": []
}
```

## Testing

Run the test suite with Composer:

```bash
composer test
```

## License

MIT
