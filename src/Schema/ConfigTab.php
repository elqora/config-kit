<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Schema;

use JsonSerializable;

final readonly class ConfigTab implements JsonSerializable
{
    /**
     * @param array<int,string> $includes
     * @param array<int,string> $excludes
     * @param array<string,mixed> $meta
     */
    public function __construct(
        public string $id,
        public string $label,
        public ?string $parentId = null,
        public array $includes = [],
        public array $excludes = [],
        public array $meta = [],
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'parentId' => $this->parentId,
            'includes' => $this->includes,
            'excludes' => $this->excludes,
            'meta' => $this->meta,
        ];
    }
}
