<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model;

use Hryvinskyi\ActivityLog\Api\ActivityStatisticsInterface;
use Hryvinskyi\ActivityLog\Model\Data\DashboardQuery;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity\Grid\Collection as GridCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Dashboard figures and the grid's operation size against the real table
 */
#[DbIsolation(true)]
class ActivityStatisticsTest extends TestCase
{
    private const NOW_MS = 1792500000000;
    private const HOUR_MS = 3_600_000;

    protected function setUp(): void
    {
        $resource = self::service(ResourceConnection::class);
        $resource->getConnection()->delete($resource->getTableName(ActivityResource::TABLE));

        $this->insert(
            'op-aaaaaaaa',
            'assistant',
            'ask catalog.product.save',
            'success',
            412.0,
            2,
            'jane.doe',
            'Desk Assistant'
        );
        $this->insert('op-aaaaaaaa', 'catalog_sync', 'Product saved', 'success', null, 2, 'jane.doe', 'Desk Assistant');
        $this->insert('op-bbbbbbbb', 'partner_api', 'POST /partner/products', 'failure', 642.0, 30, 'ERP Sync');
        $this->insert('op-cccccccc', 'partner_api', 'GET /partner/orders', 'success', 40.0, 30, 'ERP Sync');
        $this->insert(
            'op-dddddddd',
            'assistant',
            'ask catalog.product.delete',
            'denied',
            12.0,
            50,
            'jane.doe',
            'Desk Assistant'
        );
        $this->insert('op-eeeeeeee', 'partner_api', 'GET /partner/orders', 'success', 60.0, 24 * 10, 'ERP Sync');
        $this->insert('op-ffffffff', 'partner_api', 'GET /partner/orders', 'success', 60.0, 24 * 20, 'ERP Sync');
    }

    public function testTotalsCountTheRangeAndChannelsAsked(): void
    {
        $statistics = self::service(ActivityStatisticsInterface::class);
        $sevenDays = new DashboardQuery(7, ['partner_api', 'assistant', 'catalog_sync'], self::NOW_MS);

        $totals = $statistics->totals($sevenDays->getChannels(), $sevenDays->getFromMs(), $sevenDays->getToMs());
        $partnerOnly = $statistics->totals(['partner_api'], $sevenDays->getFromMs(), $sevenDays->getToMs());
        $previous = $statistics->totals(
            $sevenDays->getChannels(),
            $sevenDays->getPreviousRange()['fromMs'],
            $sevenDays->getPreviousRange()['toMs']
        );

        self::assertSame(5, $totals['total']);
        self::assertSame(1, $totals['failures']);
        self::assertSame(1, $totals['denied']);
        self::assertEqualsWithDelta((412 + 642 + 40 + 12) / 4, $totals['avgDurationMs'] ?? 0.0, 0.01);
        self::assertSame(2, $partnerOnly['total']);
        self::assertSame(1, $previous['total'], 'Only the activity of ten days ago falls in the week before.');
    }

    public function testVolumeCoversEveryDayAndAddsUpToTheTotal(): void
    {
        $query = new DashboardQuery(7, ['partner_api', 'assistant', 'catalog_sync'], self::NOW_MS);

        $volume = self::service(ActivityStatisticsInterface::class)->volumeByDay($query);

        self::assertGreaterThanOrEqual(7, count($volume['days']));
        self::assertLessThanOrEqual(8, count($volume['days']));
        self::assertSame(2, array_sum($volume['counts']['partner_api']));
        self::assertSame(2, array_sum($volume['counts']['assistant']));
        self::assertSame(1, array_sum($volume['counts']['catalog_sync']));
    }

    public function testSlowestAndTopActorsRankByWhatTheySay(): void
    {
        $query = new DashboardQuery(7, ['partner_api', 'assistant', 'catalog_sync'], self::NOW_MS);
        $statistics = self::service(ActivityStatisticsInterface::class);

        $slowest = $statistics->slowestActions($query, 10);
        $actors = $statistics->topActors($query, 10);
        $denied = $statistics->topActions($query, 10, 'denied');

        self::assertSame('POST /partner/products', $slowest[0]['action']);
        self::assertNotContains(
            'Product saved',
            array_column($slowest, 'action'),
            'No duration, no place in the ranking.'
        );
        self::assertSame(
            ['jane.doe', 'Desk Assistant', 3],
            [$actors[0]['actorName'], $actors[0]['viaClient'], $actors[0]['count']]
        );
        self::assertSame(['ERP Sync', null, 2, 1], [
            $actors[1]['actorName'],
            $actors[1]['viaClient'],
            $actors[1]['count'],
            $actors[1]['failures'],
        ]);
        self::assertSame(['ask catalog.product.delete'], array_column($denied, 'action'));
    }

    public function testTheGridKnowsHowManyActivitiesShareEachOperationOfAPageItReads(): void
    {
        $collection = self::service(GridCollection::class, [
            'mainTable' => ActivityResource::TABLE,
            'resourceModel' => ActivityResource::class,
        ]);
        $collection->addFieldToFilter('channel', 'catalog_sync')->setPageSize(20);

        $sizes = [];
        foreach ($collection->getItems() as $item) {
            $size = $item->getData('operation_size');
            self::assertIsNumeric($size);
            $sizes[] = (int) $size;
        }

        self::assertSame([2], $sizes, 'The whole operation counts, whatever the grid shows of it');
    }

    public function testACollectionReadWholeGetsNoComputedColumns(): void
    {
        $collection = self::service(GridCollection::class, [
            'mainTable' => ActivityResource::TABLE,
            'resourceModel' => ActivityResource::class,
        ]);
        $collection->addFieldToFilter('correlation_id', 'op-aaaaaaaa');

        self::assertCount(2, $collection->getItems());
        foreach ($collection->getItems() as $item) {
            self::assertFalse($item->hasData('operation_size'));
            self::assertFalse($item->hasData('change_field'));
        }
    }

    /**
     * @param string $correlationId
     * @param string $channel
     * @param string $action
     * @param string $outcome
     * @param float|null $durationMs
     * @param int $hoursAgo
     * @param string $actor
     * @param string|null $viaClient
     * @return void
     */
    private function insert(
        string $correlationId,
        string $channel,
        string $action,
        string $outcome,
        ?float $durationMs,
        int $hoursAgo,
        string $actor,
        ?string $viaClient = null
    ): void {
        $resource = self::service(ResourceConnection::class);
        $occurredAtMs = self::NOW_MS - $hoursAgo * self::HOUR_MS;
        $resource->getConnection()->insert($resource->getTableName(ActivityResource::TABLE), [
            'correlation_id' => $correlationId,
            'channel' => $channel,
            'action' => $action,
            'actor_type' => $viaClient === null ? 'integration' : 'admin',
            'actor_name' => $actor,
            'via_client' => $viaClient,
            'outcome' => $outcome,
            'duration_ms' => $durationMs,
            'occurred_at_ms' => $occurredAtMs,
            'created_at' => gmdate('Y-m-d H:i:s', intdiv($occurredAtMs, 1000)),
        ]);
    }

    /**
     * A service from the real container, typed
     *
     * @template T of object
     * @param class-string<T> $class
     * @param array<string,mixed> $arguments Build a fresh instance with these arguments instead
     * @return T
     */
    private static function service(string $class, array $arguments = []): object
    {
        $objectManager = Bootstrap::getObjectManager();
        $service = $arguments === [] ? $objectManager->get($class) : $objectManager->create($class, $arguments);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
