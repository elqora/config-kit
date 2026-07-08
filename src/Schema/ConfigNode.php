<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Schema;

use JsonSerializable;

/**
 * Marker interface for nodes that can live inside UiConfigSchema.settings.
 *
 * Node types:
 * - ConfigField  (leaf)
 * - ConfigGroup  (group with children)
 */
interface ConfigNode extends JsonSerializable
{
    public function nodeType(): string;
}