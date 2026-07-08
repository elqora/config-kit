<?php declare(strict_types=1);

namespace Timeax\ConfigKit\Runtime;

use Timeax\ConfigKit\Contracts\ConfigFieldValidator;

final class NoopConfigFieldValidator implements ConfigFieldValidator
{
    public function validate(array $data, array $rules): array
    {
        return [];
    }
}
