<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Schema;

use Closure;
use InvalidArgumentException;
use JsonSerializable;
use Elqora\ConfigKit\Support\ConfigBag;

final readonly class ConfigOption implements JsonSerializable
{
    public ?string $id;

    /**
     * @param array<int,string> $includes
     * @param array<int,string> $excludes
     * @param array<int,ConfigOption>|Closure():array<int,ConfigOption> $children
     * @param array<int,string> $excludedFromProfiles
     * @param array<string,mixed> $requires
     */
    public function __construct(
        public string|int    $value,
        public string        $label,
        ?string              $id = null,
        public array         $includes = [],
        public array         $excludes = [],
        public array|Closure $children = [],
        public array         $excludedFromProfiles = [],
        public array         $requires = [],
    )
    {
        $this->id = $id ?? self::deriveId($value);
    }

    public function forProfile(string $profile): ?self
    {
        if (ProfileExclusion::matches($profile, $this->excludedFromProfiles)) {
            return null;
        }

        $children = $this->children;
        if ($children instanceof Closure) {
            $children = fn(): array => $this->filterChildrenForProfile($profile);
        } else {
            $children = $this->filterChildrenForProfile($profile);
        }

        return new self(
            value: $this->value,
            label: $this->label,
            id: $this->id,
            includes: $this->includes,
            excludes: $this->excludes,
            children: $children,
            excludedFromProfiles: $this->excludedFromProfiles,
            requires: $this->requires,
        );
    }

    public function forRequirements(ConfigBag $bag): ?self
    {
        if (!SchemaRequirement::matches($this->requires, $bag)) {
            return null;
        }

        $children = $this->children;
        if ($children instanceof Closure) {
            $children = fn(): array => $this->filterChildrenForRequirements($bag);
        } else {
            $children = $this->filterChildrenForRequirements($bag);
        }

        return new self(
            value: $this->value,
            label: $this->label,
            id: $this->id,
            includes: $this->includes,
            excludes: $this->excludes,
            children: $children,
            excludedFromProfiles: $this->excludedFromProfiles,
            requires: $this->requires,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'label' => $this->label,
            'includes' => $this->includes,
            'excludes' => $this->excludes,
            'excludedFromProfiles' => $this->excludedFromProfiles,
            'requires' => $this->requires,
            'children' => array_map(
                static fn(ConfigOption $option) => $option->jsonSerialize(),
                $this->resolveChildren()
            ),
        ];
    }

    /**
     * @return array<int,ConfigOption>
     */
    public function resolveChildren(): array
    {
        $resolved = is_array($this->children) ? $this->children : ($this->children)();

        if (!is_array($resolved)) {
            throw new InvalidArgumentException(
                sprintf('ConfigOption "%s" children resolver must return an array of ConfigOption.', $this->id)
            );
        }

        foreach ($resolved as $index => $child) {
            if (!$child instanceof ConfigOption) {
                throw new InvalidArgumentException(
                    sprintf(
                        'ConfigOption "%s" children resolver returned invalid item at index %s; expected %s, got %s.',
                        $this->id,
                        (string) $index,
                        ConfigOption::class,
                        get_debug_type($child)
                    )
                );
            }
        }

        return $resolved;
    }

    /**
     * @return array<int,ConfigOption>
     */
    private function filterChildrenForProfile(string $profile): array
    {
        $children = [];

        foreach ($this->resolveChildren() as $child) {
            $filtered = $child->forProfile($profile);

            if ($filtered instanceof ConfigOption) {
                $children[] = $filtered;
            }
        }

        return $children;
    }

    /**
     * @return array<int,ConfigOption>
     */
    private function filterChildrenForRequirements(ConfigBag $bag): array
    {
        $children = [];

        foreach ($this->resolveChildren() as $child) {
            $filtered = $child->forRequirements($bag);

            if ($filtered instanceof ConfigOption) {
                $children[] = $filtered;
            }
        }

        return $children;
    }

    private static function deriveId(string|int $value): string
    {
        $normalized = strtolower(trim((string)$value));
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'option';
    }
}
