<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Schema;

use Elqora\ConfigKit\Support\ConfigBag;

final class SchemaRequirement
{
    private const OPERATORS = ['equals', 'not', 'in', 'notIn', 'filled', 'empty', 'regex'];

    /**
     * @param array<string,mixed> $requirements
     */
    public static function matches(array $requirements, ConfigBag $bag): bool
    {
        foreach ($requirements as $key => $condition) {
            [$exists, $value] = self::valueFor((string) $key, $bag);

            if (!self::conditionMatches($exists, $value, $condition)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0:bool,1:mixed}
     */
    private static function valueFor(string $key, ConfigBag $bag): array
    {
        if (array_key_exists($key, $bag->options)) {
            return [true, $bag->options[$key]];
        }

        if (array_key_exists($key, $bag->secrets)) {
            return [true, $bag->secrets[$key]];
        }

        return [false, null];
    }

    private static function conditionMatches(bool $exists, mixed $value, mixed $condition): bool
    {
        if (is_array($condition) && self::isOperatorObject($condition)) {
            foreach ($condition as $operator => $expected) {
                if (!self::operatorMatches((string) $operator, $exists, $value, $expected)) {
                    return false;
                }
            }

            return true;
        }

        if (is_array($condition) && self::hasStringKeys($condition)) {
            return false;
        }

        if (is_array($condition)) {
            return self::operatorMatches('in', $exists, $value, $condition);
        }

        return self::operatorMatches('equals', $exists, $value, $condition);
    }

    /**
     * @param array<mixed> $condition
     */
    private static function isOperatorObject(array $condition): bool
    {
        if ($condition === []) {
            return false;
        }

        foreach (array_keys($condition) as $key) {
            if (!is_string($key) || !in_array($key, self::OPERATORS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<mixed> $condition
     */
    private static function hasStringKeys(array $condition): bool
    {
        foreach (array_keys($condition) as $key) {
            if (is_string($key)) {
                return true;
            }
        }

        return false;
    }

    private static function operatorMatches(string $operator, bool $exists, mixed $value, mixed $expected): bool
    {
        return match ($operator) {
            'equals' => $exists && self::matchesExpected($value, $expected),
            'not' => $exists && !self::matchesExpected($value, $expected),
            'in' => $exists && self::matchesAny($value, $expected),
            'notIn' => $exists && !self::matchesAny($value, $expected),
            'filled' => $exists && self::isFilled($value) === (bool) $expected,
            'empty' => !$exists || self::isFilled($value) === false,
            'regex' => $exists && is_string($expected) && self::matchesRegex($value, $expected),
            default => false,
        };
    }

    private static function matchesExpected(mixed $value, mixed $expected): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::same($item, $expected)) {
                    return true;
                }
            }

            return false;
        }

        return self::same($value, $expected);
    }

    private static function matchesAny(mixed $value, mixed $expected): bool
    {
        $expectedValues = is_array($expected) ? $expected : [$expected];

        foreach ($expectedValues as $item) {
            if (self::matchesExpected($value, $item)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesRegex(mixed $value, string $pattern): bool
    {
        if (@preg_match($pattern, '') === false) {
            return false;
        }

        $values = is_array($value) ? $value : [$value];

        foreach ($values as $item) {
            if (preg_match($pattern, self::normalize($item)) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function same(mixed $left, mixed $right): bool
    {
        return self::normalize($left) === self::normalize($right);
    }

    private static function normalize(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private static function isFilled(mixed $value): bool
    {
        return !($value === null || $value === '' || $value === []);
    }
}
