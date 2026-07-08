<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Contracts;

use Timeax\ConfigKit\Runtime\SettingsTarget;

interface SettingsTargetResolver
{
    public function resolve(SettingsContract $provider): SettingsTarget;
}
