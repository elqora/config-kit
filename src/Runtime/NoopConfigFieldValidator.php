<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Runtime;

use Elqora\ConfigKit\Contracts\ConfigFieldValidator;

final class NoopConfigFieldValidator implements ConfigFieldValidator
{
    public function validate(array $data, array $rules): array
    {
        return [];
    }
}
