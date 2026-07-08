<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Support;

use JsonSerializable;

final class ConfigValidationResult implements JsonSerializable
{
    /** @var array<string, array<int, ConfigValidationError>> */
    private array $errors = [];

    public function __construct(public readonly bool $ok = true)
    {
    }

    public static function ok(): self
    {
        return new self(true);
    }


    public static function fail(array $errors): self
    {
        $instance = new self(false);
        if ($errors) {
            $instance->addErrors($errors);
        }
        return $instance;
    }

    public function addError(string $field, string $message, ?string $code = null): self
    {
        $this->errors[$field] ??= [];
        return $this->applyError(new ConfigValidationError($field, $message, $code));
    }

    private function applyError(ConfigValidationError $error): self
    {
        $this->errors[$error->field][] = $error;
        return $this;
    }

    /** @param array<string, array<int, string|ConfigValidationError>> $errors */
    public function addErrors(array $errors): self
    {
        foreach ($errors as $field => $errs) {
            foreach ($errs as $err) {
                if (is_string($err)) {
                    $this->addError($field, $err);
                } elseif ($err instanceof ConfigValidationError) {
                    $this->applyError($err);
                }
            }
        }
        return $this;
    }

    public function isOk(): bool
    {
        return $this->ok && $this->errors === [];
    }

    /** @return array<string, array<int, ConfigValidationError>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function jsonSerialize(): array
    {
        return [
            'ok' => $this->isOk(),
            'errors' => array_map(
                static fn(array $errs) => array_map(
                    static fn(ConfigValidationError $e) => $e->jsonSerialize(),
                    $errs
                ),
                $this->errors
            ),
        ];
    }
}