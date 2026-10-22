<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Search\Query;

use Hryvinskyi\ActivityLog\Model\Search\Query\TextWords;
use PHPUnit\Framework\TestCase;

/**
 * Plain text becomes its distinct words, at most eight, whatever was typed
 */
class TextWordsTest extends TestCase
{
    public function testSplitsOnWhitespaceAndDropsTheQuotesAroundAWord(): void
    {
        self::assertSame(
            ['MB01', 'price', '24-MB01', 'a%b'],
            (new TextWords())->of("  MB01\tprice \"24-MB01\" \n a%b ")
        );
    }

    public function testAWordTypedAgainInAnyCaseCountsOnce(): void
    {
        self::assertSame(['Price', 'bag'], (new TextWords())->of('Price price PRICE bag'));
    }

    public function testKeepsTheFirstEightDistinctWords(): void
    {
        $value = trim(str_repeat('a ', 100) . implode(' ', range('b', 'z')));

        self::assertSame(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'], (new TextWords())->of($value));
        self::assertCount(TextWords::MAX_WORDS, (new TextWords())->of($value));
    }

    public function testQuotesAloneLeaveNoWord(): void
    {
        self::assertSame([], (new TextWords())->of('"" " "'));
    }
}
