<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search;

use Hryvinskyi\ActivityLog\Model\Search\Suggestion\RecentWindow;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\FixedClock;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The recent window is the 30 × 24 hours before now, and starts at the first activity recorded in it
 */
#[DbIsolation(true)]
class RecentWindowTest extends TestCase
{
    private const NOW_MS = 1_792_500_000_000;
    private const DAY_MS = 86_400_000;

    public function testTheWindowStartsThirtyDaysBeforeNowAtItsFirstActivity(): void
    {
        $fixture = new SearchFixture();
        $fixture->clear();
        $fixture->insert(self::NOW_MS - 30 * self::DAY_MS - 1);
        $first = $fixture->insert(self::NOW_MS - 30 * self::DAY_MS);
        $fixture->insert(self::NOW_MS - 1000);
        $window = $this->window(self::NOW_MS);

        self::assertSame(self::NOW_MS - 30 * self::DAY_MS, $window->sinceMs());
        self::assertSame($first, $window->firstActivityId());

        $fixture->clear();
        self::assertSame($first, $window->firstActivityId(), 'Read once per request');
        $window->_resetState();
        self::assertNull($window->firstActivityId(), 'No recent activity');
    }

    public function testTheWindowNeverStartsBeforeTheEpoch(): void
    {
        self::assertSame(0, $this->window(1000)->sinceMs());
    }

    /**
     * @param int $nowMs
     * @return RecentWindow
     */
    private function window(int $nowMs): RecentWindow
    {
        $window = Bootstrap::getObjectManager()->create(RecentWindow::class, ['clock' => new FixedClock($nowMs)]);
        self::assertInstanceOf(RecentWindow::class, $window);

        return $window;
    }
}
