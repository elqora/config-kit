<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Schema;

use Closure;
use InvalidArgumentException;
use JsonSerializable;

final readonly class ConfigOption implements JsonSerializable
{
    public ?string $id;

    /**
     * @param array<int,string> $includes
     * @param array<int,string> $excludes
     * @param array<int,ConfigOption>|Closure():array<int,ConfigOption> $children
     */
    public function __construct(
        public string|int    $value,
        public string        $label,
        ?string              $id = null,
        public array         $includes = [],
        public array         $excludes = [],
        public array|Closure $children = []
    )
    {
        $this->id = $id ?? self::deriveId($value);
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'label' => $this->label,
            'includes' => $this->includes,
            'excludes' => $this->excludes,
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

    private static function deriveId(string|int $value): string
    {
        $normalized = strtolower(trim((string)$value));
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'option';
    }
}
