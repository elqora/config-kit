<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Schema;

use JsonSerializable;

final readonly class ConfigTab implements JsonSerializable
{
    /**
     * @param array<int,string> $includes
     * @param array<int,string> $excludes
     * @param array<string,mixed> $meta
     * @param array<int,string> $excludedFromProfiles
     */
    public function __construct(
        public string $id,
        public string $label,
        public ?string $parentId = null,
        public array $includes = [],
        public array $excludes = [],
        public array $meta = [],
        public array $excludedFromProfiles = [],
    ) {}

    public function excludedFromProfile(string $profile): bool
    {
        return ProfileExclusion::matches($profile, $this->excludedFromProfiles);
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'parentId' => $this->parentId,
            'includes' => $this->includes,
            'excludes' => $this->excludes,
            'meta' => $this->meta,
            'excludedFromProfiles' => $this->excludedFromProfiles,
        ];
    }
}
