<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search;

use Hryvinskyi\ActivityLog\Model\Search\RecentDays;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\TestFramework\Fixture\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * "The last N days" starts at local midnight N−1 days ago in the store's timezone, across daylight-saving changes
 */
class RecentDaysTest extends TestCase
{
    /**
     * @return array<string, array{int, string, string}>
     */
    public static function ranges(): array
    {
        return [
            'today, in summer time' => [1, '2026-10-24T10:00:00Z', '2026-10-23T22:00:00Z'],
            'today, on the day summer time ends' => [1, '2026-10-25T11:00:00Z', '2026-10-24T22:00:00Z'],
            'seven days over the end of summer time' => [7, '2026-10-27T11:00:00Z', '2026-10-20T22:00:00Z'],
            'two days over the start of summer time' => [2, '2026-03-30T08:00:00Z', '2026-03-28T23:00:00Z'],
            'thirty days, just after local midnight' => [30, '2026-01-31T23:30:00Z', '2026-01-02T23:00:00Z'],
        ];
    }

    #[DataProvider('ranges')]
    #[Config('general/locale/timezone', 'Europe/Copenhagen', 'store', 'default')]
    public function testTheRangeStartsAtLocalMidnight(int $days, string $now, string $expectedStart): void
    {
        $nowMs = (int) strtotime($now) * 1000 + 123;

        self::assertSame(
            (int) strtotime($expectedStart) * 1000,
            SearchFixture::service(RecentDays::class)->startMs($days, $nowMs)
        );
    }

    public function testFewerThanOneDayIsNotARange(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SearchFixture::service(RecentDays::class)->startMs(0, 1_792_500_000_000);
    }
}
