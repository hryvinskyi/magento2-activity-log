<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search;

use Hryvinskyi\ActivityLog\Api\Search\RecentSubjectsInterface;
use Hryvinskyi\ActivityLog\Model\ResourceModel\RecentSubjects;
use Hryvinskyi\ActivityLog\Model\Search\ChangeChannels;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\RecentWindow;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\FixedClock;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Recently changed subjects, from the journal's activities of the channels that record changes
 */
#[DbIsolation(true)]
class RecentSubjectsTest extends TestCase
{
    private const NOW_MS = 1_792_500_000_000;
    private const DAY_MS = 86_400_000;

    /**
     * @var SearchFixture
     */
    private SearchFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new SearchFixture();
        $this->fixture->clear();
    }

    public function testSubjectsComeMostRecentlyChangedFirstUnderTheirLatestNameWithTheirCount(): void
    {
        $this->change(self::NOW_MS - 5000, 'record', '7', 'Record #7');
        $this->change(self::NOW_MS - 4000, 'record', '8', 'Record aal-8 · Eight');
        $this->change(self::NOW_MS - 3000, 'record', '7', 'Record aal-7 · Seven');
        $this->change(self::NOW_MS - 2000, 'other', '9', 'Other #9');

        self::assertSame(
            [
                ['type' => 'other', 'id' => '9', 'label' => 'Other #9', 'count' => 1],
                ['type' => 'record', 'id' => '7', 'label' => 'Record aal-7 · Seven', 'count' => 2],
                ['type' => 'record', 'id' => '8', 'label' => 'Record aal-8 · Eight', 'count' => 1],
            ],
            $this->subjects()->recentlyChanged(['record', 'other'])
        );
        self::assertSame(['7', '8'], array_column($this->subjects()->recentlyChanged(['record']), 'id'));
    }

    public function testOnlyRecentChangesOfTheChangeChannelsCount(): void
    {
        $this->change(self::NOW_MS - 1000, 'record', '1', 'Record #1');
        $this->fixture->insert(self::NOW_MS - 900, [
            'channel' => 'zz_request', 'subject_type' => 'record', 'subject_id' => '1', 'subject_label' => 'Saved',
        ]);
        $this->change(self::NOW_MS - 31 * self::DAY_MS, 'record', '2', 'Record #2');
        $this->change(self::NOW_MS - 800, 'record', null, 'Record without an id');

        self::assertSame(
            [['type' => 'record', 'id' => '1', 'label' => 'Record #1', 'count' => 1]],
            $this->subjects()->recentlyChanged(['record'])
        );
    }

    public function testTheLimitIsKept(): void
    {
        foreach (range(5, 1) as $id) {
            $this->change(self::NOW_MS - $id * 1000, 'record', (string) $id, 'Record #' . $id);
        }

        self::assertSame(['1', '2'], array_column($this->subjects()->recentlyChanged(['record'], 2), 'id'));
        self::assertCount(1, $this->subjects()->recentlyChanged(['record'], 0));
    }

    public function testWithoutAChangeChannelOrATypeNothingIsChanged(): void
    {
        $this->change(self::NOW_MS - 1000, 'record', '1', 'Record #1');

        self::assertSame([], $this->subjects([])->recentlyChanged(['record']));
        self::assertSame([], $this->subjects()->recentlyChanged([]));
    }

    public function testTheContainerAnswersThePortWithTheJournal(): void
    {
        self::assertInstanceOf(
            RecentSubjects::class,
            Bootstrap::getObjectManager()->get(RecentSubjectsInterface::class)
        );
    }

    /**
     * An activity of the test's change channel
     *
     * @param int $ms
     * @param string $type
     * @param string|null $id
     * @param string $label
     * @return void
     */
    private function change(int $ms, string $type, ?string $id, string $label): void
    {
        $this->fixture->insert($ms, [
            'channel' => 'zz_change',
            'subject_type' => $type,
            'subject_id' => $id,
            'subject_label' => $label,
        ]);
    }

    /**
     * The journal's recent subjects, with "zz_change" or the given channels recording changes, at NOW_MS
     *
     * @param array<string, string>|null $changeChannels
     * @return RecentSubjects
     */
    private function subjects(?array $changeChannels = null): RecentSubjects
    {
        $objectManager = Bootstrap::getObjectManager();
        $subjects = $objectManager->create(RecentSubjects::class, [
            'window' => $objectManager->create(RecentWindow::class, ['clock' => new FixedClock(self::NOW_MS)]),
            'changeChannels' => $objectManager->create(ChangeChannels::class, [
                'changeChannels' => $changeChannels ?? ['zz_change' => 'zz_change'],
            ]),
        ]);
        self::assertInstanceOf(RecentSubjects::class, $subjects);

        return $subjects;
    }
}
