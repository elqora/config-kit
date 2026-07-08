<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Runtime;

final readonly class SettingsTarget
{
    public function __construct(
        public int|string $targetId,
        public string $targetType = 'settings',
        public ?string $handlerKey = null,
    ) {
    }
}
