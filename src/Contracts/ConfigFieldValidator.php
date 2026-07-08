<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Contracts;

use Timeax\ConfigKit\Support\ConfigValidationError;

interface ConfigFieldValidator
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,array<int,string>> $rules
     * @return array<string,array<int,string|ConfigValidationError>>
     */
    public function validate(array $data, array $rules): array;
}
