<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Support;

use JsonSerializable;

final readonly class ConfigValidationError implements JsonSerializable
{
    public function __construct(
        public string  $field,
        public string  $message,
        public ?string $code = null,
    )
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'field' => $this->field,
            'message' => $this->message,
            'code' => $this->code,
        ];
    }
}