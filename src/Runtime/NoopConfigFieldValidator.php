<?php declare(strict_types=1);

namespace Timeax\ConfigSchema\Runtime;

use Timeax\ConfigSchema\Contracts\ConfigFieldValidator;

final class NoopConfigFieldValidator implements ConfigFieldValidator
{
    public function validate(array $data, array $rules): array
    {
        return [];
    }
}
