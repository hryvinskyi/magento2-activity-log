<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search;

use Hryvinskyi\ActivityLog\Api\Search\ActivityActorsInterface;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\TestFramework\Fixture\DbIsolation;
use PHPUnit\Framework\TestCase;

/**
 * Who acted in given activities, through the container's port over the journal
 */
#[DbIsolation(true)]
class ActivityActorsTest extends TestCase
{
    private const NOW_MS = 1_792_500_000_000;

    /**
     * @var SearchFixture
     */
    private SearchFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new SearchFixture();
        $this->fixture->clear();
    }

    public function testNamesEachActorOfTheActivitiesOnceInNameOrder(): void
    {
        $john = $this->fixture->insert(self::NOW_MS, ['actor_name' => 'john']);
        $anna = $this->fixture->insert(self::NOW_MS + 1, ['actor_name' => 'anna']);
        $johnAgain = $this->fixture->insert(self::NOW_MS + 2, ['actor_name' => 'john']);
        $blank = $this->fixture->insert(self::NOW_MS + 3, ['actor_name' => '']);
        $this->fixture->insert(self::NOW_MS + 4, ['actor_name' => 'not asked']);

        self::assertSame(['anna', 'john'], $this->actors()->namesOf([$john, $anna, $johnAgain, $blank, 999_999_999]));
        self::assertSame(['john'], $this->actors()->namesOf([$johnAgain]));
        self::assertSame([], $this->actors()->namesOf([]));
    }

    public function testStopsAtItsLimitsOfNamesAndActivities(): void
    {
        $rows = [];
        for ($index = 0; $index < ActivityActorsInterface::MAX_NAMES + 5; $index++) {
            $rows[] = [self::NOW_MS + $index, ['actor_name' => sprintf('actor %03d', $index)]];
        }
        $this->fixture->insertMany($rows);

        self::assertCount(
            ActivityActorsInterface::MAX_NAMES,
            $this->actors()->namesOf($this->fixture->idsWhere('1 = 1'))
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->actors()->namesOf(range(1, ActivityActorsInterface::MAX_ACTIVITIES + 1));
    }

    /**
     * @return ActivityActorsInterface
     */
    private function actors(): ActivityActorsInterface
    {
        return SearchFixture::service(ActivityActorsInterface::class);
    }
}
