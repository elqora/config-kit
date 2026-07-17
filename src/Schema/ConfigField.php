<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Schema;

use Closure;
use InvalidArgumentException;
use JsonSerializable;
use Elqora\ConfigKit\Support\ConfigBag;

final readonly class ConfigField implements JsonSerializable, ConfigNode
{
    /**
     * @param array<int,string> $rules
     * @param array<int,ConfigOption>|Closure():array<int,ConfigOption> $options
     * @param array<string,mixed> $meta
     * @param array<int,string> $tabs
     * @param array<int,string> $includes
     * @param array<int,string> $excludes
     * @param array<int,string> $excludedFromProfiles
     * @param array<string,mixed> $requires
     */
    public function __construct(
        public string  $name,
        public string  $label,
        public string  $type = 'text',
        public bool    $required = false,
        public bool    $secret = false,
        public array   $rules = [],
        public mixed   $default = null,
        public ?string $helpText = null,
        public array|Closure $options = [],
        public bool    $sandbox = false,
        public array   $meta = [],

        /** Group path like "gateway" or "gateway.credentials" (optional). */
        public ?string $group = null,
        public array   $tabs = [],
        public bool    $isButton = false,
        public array   $includes = [],
        public array   $excludes = [],
        public array   $excludedFromProfiles = [],
        public array   $requires = [],
    )
    {
    }

    public function nodeType(): string
    {
        return 'field';
    }

    public function withGroup(?string $group): self
    {
        return new self(
            name: $this->name,
            label: $this->label,
            type: $this->type,
            required: $this->required,
            secret: $this->secret,
            rules: $this->rules,
            default: $this->default,
            helpText: $this->helpText,
            options: $this->options,
            sandbox: $this->sandbox,
            meta: $this->meta,
            group: $group,
            tabs: $this->tabs,
            isButton: $this->isButton,
            includes: $this->includes,
            excludes: $this->excludes,
            excludedFromProfiles: $this->excludedFromProfiles,
            requires: $this->requires,
        );
    }

    public function forProfile(string $profile): ?self
    {
        if (ProfileExclusion::matches($profile, $this->excludedFromProfiles)) {
            return null;
        }

        $options = $this->options;
        if ($options instanceof Closure) {
            $options = fn(): array => $this->filterOptionsForProfile($profile);
        } else {
            $options = $this->filterOptionsForProfile($profile);
        }

        return new self(
            name: $this->name,
            label: $this->label,
            type: $this->type,
            required: $this->required,
            secret: $this->secret,
            rules: $this->rules,
            default: $this->default,
            helpText: $this->helpText,
            options: $options,
            sandbox: $this->sandbox,
            meta: $this->meta,
            group: $this->group,
            tabs: $this->tabs,
            isButton: $this->isButton,
            includes: $this->includes,
            excludes: $this->excludes,
            excludedFromProfiles: $this->excludedFromProfiles,
            requires: $this->requires,
        );
    }

    public function forRequirements(ConfigBag $bag): ?self
    {
        if (!SchemaRequirement::matches($this->requires, $bag)) {
            return null;
        }

        $options = $this->options;
        if ($options instanceof Closure) {
            $options = fn(): array => $this->filterOptionsForRequirements($bag);
        } else {
            $options = $this->filterOptionsForRequirements($bag);
        }

        return new self(
            name: $this->name,
            label: $this->label,
            type: $this->type,
            required: $this->required,
            secret: $this->secret,
            rules: $this->rules,
            default: $this->default,
            helpText: $this->helpText,
            options: $options,
            sandbox: $this->sandbox,
            meta: $this->meta,
            group: $this->group,
            tabs: $this->tabs,
            isButton: $this->isButton,
            includes: $this->includes,
            excludes: $this->excludes,
            excludedFromProfiles: $this->excludedFromProfiles,
            requires: $this->requires,
        );
    }

    /**
     * @return array<int,ConfigOption>
     */
    public function resolveOptions(): array
    {
        $resolved = is_array($this->options) ? $this->options : ($this->options)();

        if (!is_array($resolved)) {
            throw new InvalidArgumentException(
                sprintf('ConfigField "%s" options resolver must return an array of ConfigOption.', $this->name)
            );
        }

        foreach ($resolved as $index => $option) {
            if (!$option instanceof ConfigOption) {
                throw new InvalidArgumentException(
                    sprintf(
                        'ConfigField "%s" options resolver returned invalid item at index %s; expected %s, got %s.',
                        $this->name,
                        (string) $index,
                        ConfigOption::class,
                        get_debug_type($option)
                    )
                );
            }
        }

        return $resolved;
    }

    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
            'secret' => $this->secret,
            'rules' => $this->rules,
            'default' => $this->default,
            'helpText' => $this->helpText,
            'name' => $this->name,
            'options' => array_map(
                static fn(ConfigOption $o) => $o->jsonSerialize(),
                $this->resolveOptions()
            ),
            'sandbox' => $this->sandbox,
            'meta' => $this->meta,

            // Optional, but useful for round-trips.
            'group' => $this->group,
            'tabs' => $this->tabs,
            'isButton' => $this->isButton,
            'includes' => $this->includes,
            'excludes' => $this->excludes,
            'excludedFromProfiles' => $this->excludedFromProfiles,
            'requires' => $this->requires,
        ];
    }

    /**
     * @return array<int,ConfigOption>
     */
    private function filterOptionsForProfile(string $profile): array
    {
        $options = [];

        foreach ($this->resolveOptions() as $option) {
            $filtered = $option->forProfile($profile);

            if ($filtered instanceof ConfigOption) {
                $options[] = $filtered;
            }
        }

        return $options;
    }

    /**
     * @return array<int,ConfigOption>
     */
    private function filterOptionsForRequirements(ConfigBag $bag): array
    {
        $options = [];

        foreach ($this->resolveOptions() as $option) {
            $filtered = $option->forRequirements($bag);

            if ($filtered instanceof ConfigOption) {
                $options[] = $filtered;
            }
        }

        return $options;
    }
}
