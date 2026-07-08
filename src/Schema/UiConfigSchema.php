<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Schema;

use JsonSerializable;

final readonly class UiConfigSchema implements JsonSerializable
{
    /**
     * Forti-style schema root:
     * { settings: { key: ConfigNode } }
     *
     * @param array<string, ConfigNode> $settings
     * @param array<int, ConfigTab> $tabs
     */
    public function __construct(
        public array $settings = [],
        public array $tabs = [],
    )
    {
    }

    public function with(string $key, ConfigNode $node): self
    {
        $settings = $this->settings;
        $settings[$key] = $node;

        return new self($settings, $this->tabs);
    }

    /**
     * Flatten the tree into the existing flat ConfigSchema(fields[]).
     * Optionally filter by sandbox mode:
     * - null  => include everything
     * - true  => only sandbox fields
     * - false => only live fields
     */
    public function flatten(?bool $sandbox = null): ConfigSchema
    {
        $out = [];

        $walk = static function (ConfigNode $node, ?string $groupPath) use (&$walk, &$out, $sandbox): void {
            if ($node instanceof ConfigField) {
                if ($sandbox === null || $node->sandbox === $sandbox) {
                    $out[] = $node->withGroup($groupPath);
                }
                return;
            }

            if ($node instanceof ConfigGroup) {
                $nextPath = $groupPath;
                // ConfigGroup needs a stable key/name; we’ll use the *settings key*
                // passed from the parent traversal (see below).
                foreach ($node->children as $childKey => $childNode) {
                    $walk($childNode, $nextPath);
                }
            }
        };

        foreach ($this->settings as $key => $node) {
            if ($node instanceof ConfigGroup) {
                // root group path becomes the key ("gateway", "emails", etc.)
                foreach ($node->children as $child) {
                    $walk($child, $key);
                }
            } else {
                // root-level field has no group
                $walk($node, null);
            }
        }

        return new ConfigSchema($out);
    }

    public function jsonSerialize(): array
    {
        return [
            'settings' => array_map(
                static fn(ConfigNode $n) => $n->jsonSerialize(),
                $this->settings
            ),
            'tabs' => array_map(
                static fn(ConfigTab $t) => $t->jsonSerialize(),
                $this->tabs
            ),
        ];
    }
}
