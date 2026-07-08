<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Contracts;

use Elqora\ConfigKit\Runtime\SettingsTarget;

interface SettingsTargetResolver
{
    public function resolve(SettingsContract $provider): SettingsTarget;
}
