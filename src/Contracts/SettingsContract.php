<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Contracts;

interface SettingsContract extends ProvidesConfigSchema
{
    public function name(): string;
}
