<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Runtime;

use Timeax\ConfigKit\Contracts\SettingsContract;
use Timeax\ConfigKit\Contracts\SettingsTargetResolver;

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
