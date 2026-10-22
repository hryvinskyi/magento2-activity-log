<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search;

use Hryvinskyi\ActivityLog\Api\Search\ActivityFactsInterface;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\DbIsolation;
use PHPUnit\Framework\TestCase;

/**
 * When given activities happened and who did them, through the container's port over the journal
 */
#[DbIsolation(true)]
class ActivityFactsTest extends TestCase
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

    public function testGivesTimeAndActorOfEachActivityStillInTheJournalInOneQuery(): void
    {
        $john = $this->fixture->insert(self::NOW_MS, ['actor_name' => 'john']);
        $blank = $this->fixture->insert(self::NOW_MS + 5, ['actor_name' => '']);
        $this->fixture->insert(self::NOW_MS + 9, ['actor_name' => 'not asked']);

        $connection = SearchFixture::service(ResourceConnection::class)->getConnection();
        self::assertInstanceOf(\Zend_Db_Adapter_Abstract::class, $connection);
        $profiler = $connection->getProfiler();
        $profiler->clear();
        $profiler->setEnabled(true);
        try {
            $facts = $this->facts()->of([$john, $blank, $john, 999_999_999, 0]);
            $queries = $profiler->getTotalNumQueries();
        } finally {
            $profiler->setEnabled(false);
            $profiler->clear();
        }

        self::assertSame([$john, $blank], array_keys($facts));
        self::assertSame(
            [[$john, self::NOW_MS, 'john'], [$blank, self::NOW_MS + 5, '']],
            array_map(
                static fn ($fact): array => [$fact->getActivityId(), $fact->getOccurredAtMs(), $fact->getActorName()],
                array_values($facts)
            )
        );
        self::assertSame(1, $queries);
        self::assertSame([], $this->facts()->of([]));
    }

    public function testRefusesMoreActivitiesThanItAnswersAboutAtOnce(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->facts()->of(range(1, ActivityFactsInterface::MAX_ACTIVITIES + 1));
    }

    /**
     * @return ActivityFactsInterface
     */
    private function facts(): ActivityFactsInterface
    {
        return SearchFixture::service(ActivityFactsInterface::class);
    }
}
