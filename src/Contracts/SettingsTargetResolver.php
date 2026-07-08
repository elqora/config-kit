<?php declare(strict_types=1);

namespace Timeax\ConfigSchema\Contracts;

use Timeax\ConfigSchema\Runtime\SettingsTarget;

interface SettingsTargetResolver
{
    public function resolve(SettingsContract $provider): SettingsTarget;
}
