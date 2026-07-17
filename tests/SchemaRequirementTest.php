<?php declare(strict_types=1);

namespace Elqora\ConfigKit\Tests;

use PHPUnit\Framework\TestCase;
use Elqora\ConfigKit\Schema\SchemaRequirement;
use Elqora\ConfigKit\Support\ConfigBag;

final class SchemaRequirementTest extends TestCase
{
    public function testScalarAndOneOfShorthandRequirements(): void
    {
        $bag = new ConfigBag(options: [
            'payment_method' => 'card',
            'country' => 'ng',
        ]);

        self::assertTrue(SchemaRequirement::matches(['payment_method' => 'card'], $bag));
        self::assertTrue(SchemaRequirement::matches(['payment_method' => ['card', 'bank']], $bag));
        self::assertFalse(SchemaRequirement::matches(['payment_method' => 'bank'], $bag));
        self::assertFalse(SchemaRequirement::matches(['country' => ['gh', 'ke']], $bag));
    }

    public function testSupportedOperators(): void
    {
        $bag = new ConfigBag(options: [
            'payment_method' => 'card-live',
            'country' => 'ng',
            'count' => 2,
        ]);

        self::assertTrue(SchemaRequirement::matches(['payment_method' => ['equals' => 'card-live']], $bag));
        self::assertTrue(SchemaRequirement::matches(['payment_method' => ['not' => 'bank']], $bag));
        self::assertTrue(SchemaRequirement::matches(['country' => ['in' => ['ng', 'gh']]], $bag));
        self::assertTrue(SchemaRequirement::matches(['country' => ['notIn' => ['ke', 'za']]], $bag));
        self::assertTrue(SchemaRequirement::matches(['payment_method' => ['regex' => '/^card-/']], $bag));
        self::assertTrue(SchemaRequirement::matches(['count' => ['equals' => '2']], $bag));
        self::assertFalse(SchemaRequirement::matches(['payment_method' => ['regex' => '/^bank-/']], $bag));
        self::assertFalse(SchemaRequirement::matches(['country' => ['unknown' => 'ng']], $bag));
    }

    public function testMissingAndFilledOrEmptyValues(): void
    {
        $bag = new ConfigBag(options: [
            'filled_zero' => 0,
            'filled_false' => false,
            'empty_string' => '',
            'empty_array' => [],
        ]);

        self::assertFalse(SchemaRequirement::matches(['missing' => 'value'], $bag));
        self::assertTrue(SchemaRequirement::matches(['missing' => ['empty' => true]], $bag));
        self::assertTrue(SchemaRequirement::matches(['filled_zero' => ['filled' => true]], $bag));
        self::assertTrue(SchemaRequirement::matches(['filled_false' => ['filled' => true]], $bag));
        self::assertTrue(SchemaRequirement::matches(['empty_string' => ['empty' => true]], $bag));
        self::assertTrue(SchemaRequirement::matches(['empty_array' => ['empty' => true]], $bag));
        self::assertFalse(SchemaRequirement::matches(['empty_string' => ['filled' => true]], $bag));
    }

    public function testArrayValuesUseContainsSemantics(): void
    {
        $bag = new ConfigBag(options: [
            'features' => ['card', 'wallet'],
        ]);

        self::assertTrue(SchemaRequirement::matches(['features' => 'card'], $bag));
        self::assertTrue(SchemaRequirement::matches(['features' => ['card', 'bank']], $bag));
        self::assertTrue(SchemaRequirement::matches(['features' => ['in' => ['wallet', 'bank']]], $bag));
        self::assertTrue(SchemaRequirement::matches(['features' => ['not' => 'bank']], $bag));
        self::assertTrue(SchemaRequirement::matches(['features' => ['notIn' => ['bank', 'transfer']]], $bag));
        self::assertTrue(SchemaRequirement::matches(['features' => ['regex' => '/^wal/']], $bag));
        self::assertFalse(SchemaRequirement::matches(['features' => ['not' => 'card']], $bag));
        self::assertFalse(SchemaRequirement::matches(['features' => ['notIn' => ['wallet']]], $bag));
    }
}
