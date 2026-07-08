<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Runtime;

use Elqora\ConfigKit\Contracts\SettingsContract;
use Elqora\ConfigKit\Contracts\SettingsTargetResolver;

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
