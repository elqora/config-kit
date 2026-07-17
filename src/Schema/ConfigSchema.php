<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Schema;

use JsonSerializable;

readonly class ConfigSchema implements JsonSerializable
{
    /** @param array<int,ConfigField> $fields */
    public function __construct(public array $fields = [])
    {
    }

    /**
     * Return a schema containing only fields matching the requested sandbox mode.
     */
    final public function forSandbox(bool $sandbox): self
    {
        $fields = array_values(array_filter(
            $this->fields,
            static fn(ConfigField $f): bool => $f->sandbox === $sandbox
        ));

        return new self($fields);
    }

    public function forLive(): self
    {
        return $this->forSandbox(false);
    }

    public function forTest(): self
    {
        return $this->forSandbox(true);
    }

    final public function forProfile(string $profile): self
    {
        $fields = [];

        foreach ($this->fields as $field) {
            $filtered = $field->forProfile($profile);

            if ($filtered instanceof ConfigField) {
                $fields[] = $filtered;
            }
        }

        return new self($fields);
    }

    /** @return array<int,string> field names for the requested sandbox mode */
    public function keysForSandbox(bool $sandbox): array
    {
        $out = [];
        foreach ($this->fields as $f) {
            if ($f->sandbox === $sandbox) {
                $out[] = $f->name;
            }
        }
        return $out;
    }

    final public function toUiConfigSchema(): UiConfigSchema
    {
        $settings = [];

        foreach ($this->fields as $field) {
            $group = $field->group;

            if ($group === null || $group === '') {
                // no group, directly at root
                $settings[$field->name] = $field;
                continue;
            }

            // group path: "gateway" or "gateway.credentials"
            $parts = array_values(array_filter(explode('.', $group), static fn($p) => $p !== ''));

            // ensure top-level group exists
            $rootKey = array_shift($parts);
            $settings[$rootKey] ??= new ConfigGroup(label: $rootKey, children: []);

            if (!($settings[$rootKey] instanceof ConfigGroup)) {
                // collision: rootKey already used by a field — keep field, and tuck groups under "__groups"
                $settings['__groups'] ??= new ConfigGroup(label: 'Groups', children: []);
                $rootKey = '__groups';
            }

            /** @var ConfigGroup $cursor */
            $cursor = $settings[$rootKey];

            // walk/build nested groups
            foreach ($parts as $p) {
                $child = $cursor->children[$p] ?? null;
                if (!($child instanceof ConfigGroup)) {
                    $child = new ConfigGroup(label: $p, children: []);
                    $cursor = $cursor->withChild($p, $child);
                } else {
                    $cursor = $child;
                }
            }

            // finally place field under the final group
            // remove group on the stored field? your call:
            $finalField = $field; // or $field->withGroup($group)

            // attach field using its name as key
            // (you can also use a separate key if you need aliasing)
            if ($cursor === $settings[$rootKey]) {
                // root group has no nested path
                $settings[$rootKey] = $cursor->withChild($field->name, $finalField);
            } else {
                // we rebuilt cursor locally; simplest: rebuild from scratch via helper below
                $settings[$rootKey] = self::insertIntoGroup($settings[$rootKey], $parts, $field->name, $finalField);
            }
        }

        return new UiConfigSchema($settings);
    }

    /**
     * @param ConfigGroup $group
     * @param array<int,string> $pathParts
     * @param string $fieldKey
     * @param ConfigNode $node
     * @return ConfigGroup
     */
    private static function insertIntoGroup(ConfigGroup $group, array $pathParts, string $fieldKey, ConfigNode $node): ConfigGroup
    {
        if ($pathParts === []) {
            return $group->withChild($fieldKey, $node);
        }

        $head = array_shift($pathParts);
        $child = $group->children[$head] ?? null;
        if (!($child instanceof ConfigGroup)) {
            $child = new ConfigGroup(label: $head, children: []);
        }

        $child = self::insertIntoGroup($child, $pathParts, $fieldKey, $node);
        return $group->withChild($head, $child);
    }

    public function jsonSerialize(): array
    {
        return [
            'fields' => array_map(
                static fn(ConfigField $f) => $f->jsonSerialize(),
                $this->fields
            ),
        ];
    }
}
