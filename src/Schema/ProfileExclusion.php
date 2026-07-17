<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Schema;

final class ProfileExclusion
{
    /**
     * @param array<int,string> $rules
     */
    public static function matches(string $profile, array $rules): bool
    {
        $profile = trim($profile);

        foreach ($rules as $rule) {
            $rule = trim($rule);

            if ($rule === '') {
                continue;
            }

            if ($profile === 'default') {
                if ($rule === 'default') {
                    return true;
                }

                continue;
            }

            if ($rule === $profile) {
                return true;
            }

            if (self::isRegex($rule) && @preg_match($rule, '') !== false && preg_match($rule, $profile) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function isRegex(string $rule): bool
    {
        if (strlen($rule) < 3 || $rule[0] !== '/') {
            return false;
        }

        $lastSlash = strrpos($rule, '/');

        return is_int($lastSlash) && $lastSlash > 0;
    }
}
