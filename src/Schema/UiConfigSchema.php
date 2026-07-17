<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Schema;

use JsonSerializable;
use Elqora\ConfigKit\Support\ConfigBag;

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

    public function forProfile(string $profile): self
    {
        $settings = [];

        foreach ($this->settings as $key => $node) {
            $filtered = $this->nodeForProfile($node, $profile);

            if ($filtered instanceof ConfigNode) {
                $settings[$key] = $filtered;
            }
        }

        return new self(
            settings: $settings,
            tabs: array_values(array_filter(
                $this->tabs,
                static fn(ConfigTab $tab): bool => !$tab->excludedFromProfile($profile),
            )),
        );
    }

    public function forRequirements(ConfigBag $bag): self
    {
        $settings = [];

        foreach ($this->settings as $key => $node) {
            $filtered = $this->nodeForRequirements($node, $bag);

            if ($filtered instanceof ConfigNode) {
                $settings[$key] = $filtered;
            }
        }

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

    private function nodeForProfile(ConfigNode $node, string $profile): ?ConfigNode
    {
        if ($node instanceof ConfigField) {
            return $node->forProfile($profile);
        }

        if ($node instanceof ConfigGroup) {
            return $node->forProfile($profile);
        }

        return $node;
    }

    private function nodeForRequirements(ConfigNode $node, ConfigBag $bag): ?ConfigNode
    {
        if ($node instanceof ConfigField) {
            return $node->forRequirements($bag);
        }

        if ($node instanceof ConfigGroup) {
            return $node->forRequirements($bag);
        }

        return $node;
    }
}
