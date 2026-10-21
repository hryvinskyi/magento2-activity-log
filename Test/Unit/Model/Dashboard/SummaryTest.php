<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Dashboard;

use Hryvinskyi\ActivityLog\Api\ActivityStatisticsInterface;
use Hryvinskyi\ActivityLog\Model\Dashboard\Summary;
use Hryvinskyi\ActivityLog\Model\Data\DashboardQuery;
use PHPUnit\Framework\TestCase;

class SummaryTest extends TestCase
{
    private const NOW_MS = 1792500000000;

    public function testToneFollowsWhatIsGoodForTheStore(): void
    {
        $cards = $this->cards(
            ['total' => 1200, 'avgDurationMs' => 80.0, 'failures' => 36, 'denied' => 17],
            ['total' => 1000, 'avgDurationMs' => 100.0, 'failures' => 20, 'denied' => 9]
        );

        $describe = static fn (array $card): array => [$card['direction'], $card['tone'], $card['delta']];
        self::assertSame(['up', 'good', '+20%'], $describe($cards['total']));
        self::assertSame(['down', 'good', '−20%'], $describe($cards['duration']));
        self::assertSame(['up', 'bad', '+1.0 pt'], [
            $cards['failure_rate']['direction'],
            $cards['failure_rate']['tone'],
            $cards['failure_rate']['delta'],
        ]);
        self::assertSame('3.0', $cards['failure_rate']['value']);
        self::assertSame(['up', 'bad'], [$cards['denied']['direction'], $cards['denied']['tone']]);
    }

    public function testAnEmptyPeriodSaysSo(): void
    {
        $cards = $this->cards(
            ['total' => 0, 'avgDurationMs' => null, 'failures' => 0, 'denied' => 0],
            ['total' => 40, 'avgDurationMs' => 90.0, 'failures' => 1, 'denied' => 0]
        );

        foreach ($cards as $card) {
            self::assertTrue($card['empty'], $card['key']);
        }
    }

    public function testNoChangeIsNeutral(): void
    {
        $same = ['total' => 10, 'avgDurationMs' => 50.0, 'failures' => 1, 'denied' => 0];

        foreach ($this->cards($same, $same) as $card) {
            self::assertSame('neutral', $card['tone'], $card['key']);
            self::assertSame('flat', $card['direction'], $card['key']);
        }
    }

    /**
     * @param array{total:int,avgDurationMs:float|null,failures:int,denied:int} $now
     * @param array{total:int,avgDurationMs:float|null,failures:int,denied:int} $before
     * @return array<string, array{key: string, label: string, value: string, unit: string, delta: string,
     *     direction: string, tone: string, empty: bool}>
     */
    private function cards(array $now, array $before): array
    {
        $query = new DashboardQuery(7, ['rest'], self::NOW_MS);
        $statistics = $this->createStub(ActivityStatisticsInterface::class);
        $statistics->method('totals')->willReturnCallback(
            static fn (array $channels, int $fromMs): array => $fromMs === $query->getFromMs() ? $now : $before
        );

        $cards = [];
        foreach ((new Summary($statistics))->cards($query) as $card) {
            $cards[$card['key']] = $card;
        }

        return $cards;
    }
}
