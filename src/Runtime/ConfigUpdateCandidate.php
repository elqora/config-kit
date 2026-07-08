<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Runtime;

use Elqora\ConfigKit\Support\ConfigBag;

final readonly class ConfigUpdateCandidate
{
    /**
     * @param array<string,mixed> $options
     * @param array<string,mixed> $secrets
     */
    public function __construct(
        public ConfigBag $bag,
        public array $options,
        public array $secrets,
    ) {
    }
}
