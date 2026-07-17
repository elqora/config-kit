<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Runtime;

use Elqora\ConfigKit\Contracts\ConfigFieldValidator;
use Elqora\ConfigKit\Schema\ConfigField;
use Elqora\ConfigKit\Schema\ConfigGroup;
use Elqora\ConfigKit\Schema\ConfigNode;
use Elqora\ConfigKit\Schema\ConfigOption;
use Elqora\ConfigKit\Schema\ConfigTab;
use Elqora\ConfigKit\Schema\UiConfigSchema;
use Elqora\ConfigKit\Support\ConfigBag;
use Elqora\ConfigKit\Support\ConfigValidationError;

final readonly class ConfigSchemaStore
{
    public function __construct(
        private ConfigFieldValidator $validator = new NoopConfigFieldValidator(),
    ) {
    }

    /**
     * @return array{settings:array<int,array<string,mixed>>,tabs:array<int,array<string,mixed>>}
     */
    public function toFrontendSettings(UiConfigSchema $schema, ConfigBag $bag): array
    {
        $settings = [];

        foreach ($schema->settings as $key => $node) {
            $settings[] = [
                'schemaKey' => (string) $key,
                ...$this->nodeToFrontend($node, $bag),
            ];
        }

        return [
            'settings' => $settings,
            'tabs' => array_map(
                fn(ConfigTab $tab): array => $this->tabToFrontend($tab),
                $schema->tabs,
            ),
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    public function candidate(
        UiConfigSchema $schema,
        ConfigBag $current,
        array $values,
        bool $allowClearSecrets = false,
    ): ConfigUpdateCandidate {
        $flat = $schema->flatten()->forSandbox($current->sandbox);
        $options = $current->options;
        $secrets = $current->secrets;

        foreach ($flat->fields as $field) {
            $key = $field->name;

            if (!array_key_exists($key, $values)) {
                continue;
            }

            $value = $values[$key];

            if ($field->secret) {
                if ($value === '***') {
                    continue;
                }

                if (($value === null || $value === '') && !$allowClearSecrets) {
                    continue;
                }

                if ($value === null || $value === '') {
                    unset($secrets[$key]);
                } else {
                    $secrets[$key] = $value;
                }

                continue;
            }

            if ($value === null) {
                unset($options[$key]);
                continue;
            }

            $options[$key] = $value;
        }

        return new ConfigUpdateCandidate(
            bag: new ConfigBag(
                sandbox: $current->sandbox,
                options: $options,
                secrets: $secrets,
            ),
            options: $options,
            secrets: $secrets,
        );
    }

    /**
     * @return array<string,array<int,ConfigValidationError>>
     */
    public function validateBag(UiConfigSchema $schema, ConfigBag $bag): array
    {
        $flat = $schema->flatten()->forSandbox($bag->sandbox);
        $data = [];
        $rules = [];

        foreach ($flat->fields as $field) {
            $data[$field->name] = $this->effectiveFieldValue($field, $bag);

            if ($field->rules !== []) {
                $rules[$field->name] = $field->rules;
            }
        }

        if ($rules === []) {
            return [];
        }

        return $this->normalizeValidationErrors($this->validator->validate($data, $rules));
    }

    public function contractBag(UiConfigSchema $schema, ConfigBag $bag): ConfigBag
    {
        $flat = $schema->flatten()->forSandbox($bag->sandbox);
        $options = [];
        $secrets = [];

        foreach ($flat->fields as $field) {
            if ($field->secret) {
                if (array_key_exists($field->name, $bag->secrets)) {
                    $secrets[$field->name] = $bag->secrets[$field->name];
                }

                continue;
            }

            if (array_key_exists($field->name, $bag->options)) {
                $options[$field->name] = $bag->options[$field->name];
            }
        }

        return new ConfigBag(
            sandbox: $bag->sandbox,
            options: $options,
            secrets: $secrets,
        );
    }

    private function nodeToFrontend(ConfigNode $node, ConfigBag $bag): array
    {
        if ($node instanceof ConfigGroup) {
            $children = [];

            foreach ($node->children as $childKey => $childNode) {
                $children[$childKey] = $this->nodeToFrontend($childNode, $bag);
            }

            return [
                'type' => 'group',
                'label' => $node->label,
                'is_required' => $node->required,
                'required' => $node->required,
                'meta' => $node->meta,
                'tabs' => $node->tabs,
                'includes' => $node->includes,
                'excludes' => $node->excludes,
                'excludedFromProfiles' => $node->excludedFromProfiles,
                'requires' => $node->requires,
                'children' => $children,
            ];
        }

        /** @var ConfigField $node */
        $value = $node->secret
            ? ($bag->secret($node->name) !== null ? '***' : null)
            : $bag->option($node->name);

        return [
            'label' => $node->label,
            'type' => $node->type,
            'key' => $node->name,
            'value' => $value,
            'defaultValue' => $value ?? $node->default,
            'is_required' => $node->required,
            'is_sensitive' => $node->secret,
            'name' => $node->name,
            'required' => $node->required,
            'secret' => $node->secret,
            'default' => $node->default,
            'helpText' => $node->helpText,
            'rules' => $node->rules,
            'sandbox' => $node->sandbox,
            'group' => $node->group,
            'tabs' => $node->tabs,
            'isButton' => $node->isButton,
            'includes' => $node->includes,
            'excludes' => $node->excludes,
            'excludedFromProfiles' => $node->excludedFromProfiles,
            'requires' => $node->requires,
            'options' => array_map(
                fn(ConfigOption $option): array => $this->optionToFrontend($option),
                $node->resolveOptions(),
            ),
            'meta' => $node->meta,
        ];
    }

    private function tabToFrontend(ConfigTab $tab): array
    {
        return [
            'id' => $tab->id,
            'label' => $tab->label,
            'parentId' => $tab->parentId,
            'includes' => $tab->includes,
            'excludes' => $tab->excludes,
            'meta' => $tab->meta,
            'excludedFromProfiles' => $tab->excludedFromProfiles,
        ];
    }

    private function optionToFrontend(ConfigOption $option): array
    {
        $serialized = $option->jsonSerialize();
        $rawValue = $serialized['value'] ?? $option->value;
        $rawLabel = $serialized['label'] ?? $option->label;

        $payload = [
            'id' => $serialized['id'] ?? null,
            'value' => $rawValue,
            'label' => $this->normalizeOptionLabel($rawLabel, $rawValue),
            'includes' => is_array($serialized['includes'] ?? null) ? $serialized['includes'] : [],
            'excludes' => is_array($serialized['excludes'] ?? null) ? $serialized['excludes'] : [],
            'excludedFromProfiles' => is_array($serialized['excludedFromProfiles'] ?? null) ? $serialized['excludedFromProfiles'] : [],
            'requires' => is_array($serialized['requires'] ?? null) ? $serialized['requires'] : [],
        ];

        if (isset($serialized['children']) && is_array($serialized['children']) && $serialized['children'] !== []) {
            $payload['children'] = array_map(
                fn(array $child): array => $this->serializedOptionToFrontend($child),
                $serialized['children'],
            );
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $option
     * @return array<string,mixed>
     */
    private function serializedOptionToFrontend(array $option): array
    {
        $rawValue = $option['value'] ?? '';
        $rawLabel = $option['label'] ?? $rawValue;

        $payload = [
            'id' => $option['id'] ?? null,
            'value' => $rawValue,
            'label' => $this->normalizeOptionLabel($rawLabel, $rawValue),
            'includes' => is_array($option['includes'] ?? null) ? $option['includes'] : [],
            'excludes' => is_array($option['excludes'] ?? null) ? $option['excludes'] : [],
            'excludedFromProfiles' => is_array($option['excludedFromProfiles'] ?? null) ? $option['excludedFromProfiles'] : [],
            'requires' => is_array($option['requires'] ?? null) ? $option['requires'] : [],
        ];

        if (isset($option['children']) && is_array($option['children']) && $option['children'] !== []) {
            $payload['children'] = array_map(
                fn(array $child): array => $this->serializedOptionToFrontend($child),
                $option['children'],
            );
        }

        return $payload;
    }

    private function normalizeOptionLabel(mixed $label, mixed $value): string
    {
        $labelString = is_string($label) ? trim($label) : '';

        if ($labelString === '') {
            return $this->humanizeToken((string) $value);
        }

        return $this->looksTokenLike($labelString)
            ? $this->humanizeToken($labelString)
            : $labelString;
    }

    private function looksTokenLike(string $value): bool
    {
        return str_contains($value, '_')
            || str_contains($value, '-')
            || preg_match('/[a-z][A-Z]/', $value) === 1
            || preg_match('/^[a-z0-9]+$/', $value) === 1;
    }

    private function humanizeToken(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $normalized) ?? $normalized;
        $normalized = str_replace(['_', '-'], ' ', $normalized);
        $words = preg_split('/\s+/', strtolower($normalized)) ?: [];
        $acronyms = ['id', 'api', 'url', 'ip', 'uuid', 'ui', 'json', 'http', 'https', 'sms', 'smtp'];

        return implode(' ', array_map(
            static fn(string $word): string => in_array($word, $acronyms, true)
                ? strtoupper($word)
                : ucfirst($word),
            array_filter($words, static fn(string $word): bool => $word !== ''),
        ));
    }

    private function effectiveFieldValue(ConfigField $field, ConfigBag $bag): mixed
    {
        if ($field->secret) {
            return array_key_exists($field->name, $bag->secrets)
                ? $bag->secret($field->name)
                : $field->default;
        }

        return array_key_exists($field->name, $bag->options)
            ? $bag->option($field->name)
            : $field->default;
    }

    /**
     * @param array<string,array<int,string|ConfigValidationError>> $errors
     * @return array<string,array<int,ConfigValidationError>>
     */
    private function normalizeValidationErrors(array $errors): array
    {
        $out = [];

        foreach ($errors as $field => $items) {
            foreach ($items as $item) {
                if ($item instanceof ConfigValidationError) {
                    $out[$field][] = $item;
                } elseif (is_string($item)) {
                    $out[$field][] = new ConfigValidationError($field, $item);
                }
            }
        }

        return $out;
    }
}
