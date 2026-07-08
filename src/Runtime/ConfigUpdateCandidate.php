<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Runtime;

use Timeax\ConfigKit\Support\ConfigBag;

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
