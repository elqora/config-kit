<?php declare(strict_types=1);

namespace Timeax\ConfigSchema\Runtime;

use Timeax\ConfigSchema\Contracts\SettingsContract;
use Timeax\ConfigSchema\Contracts\SettingsTargetResolver;

final class DefaultSettingsTargetResolver implements SettingsTargetResolver
{
    public function resolve(SettingsContract $provider): SettingsTarget
    {
        return new SettingsTarget(
            targetId: $provider->name(),
            targetType: 'settings',
        );
    }
}
