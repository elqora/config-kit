<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Schema;

final readonly class ConfigGroup implements ConfigNode
{
    /**
     * @param array<string, ConfigNode> $children
     * @param array<string, mixed> $meta
     * @param array<int,string> $tabs
     * @param array<int,string> $includes
     * @param array<int,string> $excludes
     */
    public function __construct(
        public string $label,
        public bool $required = false,
        public array $children = [],
        public array $meta = [],
        public array $tabs = [],
        public array $includes = [],
        public array $excludes = [],
    ) {}

    public function nodeType(): string
    {
        return 'group';
    }

    public function withChild(string $key, ConfigNode $node): self
    {
        $children = $this->children;
        $children[$key] = $node;

        return new self(
            label: $this->label,
            required: $this->required,
            children: $children,
            meta: $this->meta,
            tabs: $this->tabs,
            includes: $this->includes,
            excludes: $this->excludes,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => 'group',
            'label' => $this->label,
            'required' => $this->required,
            'meta' => $this->meta,
            'tabs' => $this->tabs,
            'includes' => $this->includes,
            'excludes' => $this->excludes,
            'children' => array_map(
                static fn(ConfigNode $n) => $n->jsonSerialize(),
                $this->children
            ),
        ];
    }
}
