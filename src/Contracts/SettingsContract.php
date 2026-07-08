<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Contracts;

interface SettingsContract extends ProvidesConfigSchema
{
    public function name(): string;
}
