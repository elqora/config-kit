<?php declare(strict_types=1);

namespace Timeax\ConfigSchema\Runtime;

final readonly class SettingsBag
{
    /**
     * @param array<string,mixed> $values
     */
    public function __construct(
        private SettingsManager $manager,
        private string $name,
        private ?string $profile,
        private bool $sandbox,
        private array $values = [],
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function profile(): ?string
    {
        return $this->profile;
    }

    public function sandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function get(string $path = '', mixed $default = null): mixed
    {
        $path = trim($path);

        if ($path === '') {
            return $this->values !== [] ? $this->values : $default;
        }

        $cursor = $this->values;

        foreach (explode('.', $path) as $segment) {
            if ($segment === '') {
                return $default;
            }

            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return $default;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    /**
     * @param array<string,mixed> $values
     * @param array<string,mixed> $options
     */
    public function apply(array $values, array $options = []): self
    {
        return $this->manager->apply($this->name, $values, $this->profile, [
            'sandbox' => $this->sandbox,
            ...$options,
        ]);
    }

    /**
     * @param array<string,mixed> $options
     */
    public function set(string $field, mixed $value, array $options = []): self
    {
        return $this->apply([$field => $value], $options);
    }
}
