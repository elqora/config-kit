<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Runtime;

use InvalidArgumentException;
use Timeax\ConfigKit\Contracts\SettingsContract;

final class SettingsProviderRegistry
{
    /** @var array<string,SettingsContract> */
    private array $providers = [];

    /**
     * @param iterable<int|string,SettingsContract> $providers
     */
    public function __construct(iterable $providers = [])
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    public function register(SettingsContract $provider): void
    {
        $name = $this->normalizeName($provider->name());

        if (isset($this->providers[$name])) {
            throw new InvalidArgumentException(sprintf('Settings provider [%s] is already registered.', $name));
        }

        $this->providers[$name] = $provider;
    }

    public function get(string $name): SettingsContract
    {
        $name = $this->normalizeName($name);

        if (!isset($this->providers[$name])) {
            throw new InvalidArgumentException(sprintf('Unknown settings provider [%s].', $name));
        }

        return $this->providers[$name];
    }

    /**
     * @return array<string,SettingsContract>
     */
    public function all(): array
    {
        return $this->providers;
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Settings provider name cannot be empty.');
        }

        return $name;
    }
}
