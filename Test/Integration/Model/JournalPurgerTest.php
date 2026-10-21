<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model;

use Hryvinskyi\ActivityLog\Api\ActivityPrunerInterface;
use Hryvinskyi\ActivityLog\Api\ChannelPoolInterface;
use Hryvinskyi\ActivityLog\Api\MillisecondClockInterface;
use Hryvinskyi\ActivityLog\Model\Channel\Channel;
use Hryvinskyi\ActivityLog\Model\Channel\ChannelPool;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Hryvinskyi\ActivityLog\Model\Retention\BatchDeleter;
use Hryvinskyi\ActivityLog\Model\Retention\JournalPurger;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Retention against the real table
 */
#[DbIsolation(true)]
class JournalPurgerTest extends TestCase
{
    private const NOW_MS = 1792500000000;
    private const DAY_MS = 86_400_000;

    #[Config('test_channels/rest/retention', '30')]
    #[Config('test_channels/admin/retention', '90')]
    #[Config('hryvinskyi_activity_log/retention/orphan_days', '10')]
    public function testEachChannelKeepsItsOwnRetentionAndUnregisteredChannelsTheOrphanOne(): void
    {
        $restOld = $this->insert('rest', 31);
        $restRecent = $this->insert('rest', 29);
        $adminMiddle = $this->insert('admin', 60);
        $adminOld = $this->insert('admin', 91);
        $selfManaged = $this->insert('entity', 400);
        $orphanOld = $this->insert('uninstalled', 11);
        $orphanRecent = $this->insert('uninstalled', 9);

        $removed = $this->purger([
            'rest' => $this->channel('rest', 'test_channels/rest/retention'),
            'admin' => $this->channel('admin', 'test_channels/admin/retention'),
            'entity' => $this->channel('entity', ''),
        ])->purge();

        self::assertSame(3, $removed);
        self::assertSame(
            [$restRecent, $adminMiddle, $selfManaged, $orphanRecent],
            $this->remaining([$restOld, $restRecent, $adminMiddle, $adminOld, $selfManaged, $orphanOld, $orphanRecent])
        );
    }

    public function testWorksThroughABacklogInBatches(): void
    {
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->insert('uninstalled', 365);
        }

        $removed = $this->purger([], 2)->purge();

        self::assertSame(5, $removed);
        self::assertSame([], $this->remaining($ids));
    }

    public function testThePrunerRemovesOnlyTheChannelAndSubjectTypesAskedFor(): void
    {
        $productOld = $this->insert('entity', 40, 'product');
        $productRecent = $this->insert('entity', 20, 'product');
        $blockOld = $this->insert('entity', 40, 'cms_block');
        $otherChannel = $this->insert('admin', 40, 'product');

        $removed = self::service(ActivityPrunerInterface::class)
            ->prune('entity', self::NOW_MS - 30 * self::DAY_MS, ['product']);

        self::assertSame(1, $removed);
        self::assertSame(
            [$productRecent, $blockOld, $otherChannel],
            $this->remaining([$productOld, $productRecent, $blockOld, $otherChannel])
        );
        self::assertSame(
            1,
            self::service(ActivityPrunerInterface::class)->prune('entity', self::NOW_MS - 30 * self::DAY_MS)
        );
    }

    /**
     * @param array<string,Channel> $channels
     * @param int $batchSize
     * @return JournalPurger
     */
    private function purger(array $channels, int $batchSize = 5000): JournalPurger
    {
        $clock = $this->createStub(MillisecondClockInterface::class);
        $clock->method('nowMs')->willReturn(self::NOW_MS);

        return self::service(JournalPurger::class, [
            'channelPool' => self::service(ChannelPool::class, ['channels' => $channels]),
            'clock' => $clock,
            'batchDeleter' => self::service(BatchDeleter::class, ['batchSize' => $batchSize]),
        ]);
    }

    /**
     * @param string $code
     * @param string $retentionPath
     * @return Channel
     */
    private function channel(string $code, string $retentionPath): Channel
    {
        return self::service(Channel::class, [
            'code' => $code,
            'label' => ucfirst($code),
            'retentionConfigPath' => $retentionPath,
        ]);
    }

    /**
     * Insert a row that happened the given number of days before NOW_MS
     *
     * @param string $channel
     * @param int $daysAgo
     * @param string|null $subjectType
     * @return int
     */
    private function insert(string $channel, int $daysAgo, ?string $subjectType = null): int
    {
        $resource = self::service(ResourceConnection::class);
        $connection = $resource->getConnection();
        $table = $resource->getTableName(ActivityResource::TABLE);
        $occurredAtMs = self::NOW_MS - $daysAgo * self::DAY_MS;
        $correlationId = bin2hex(random_bytes(8));
        $connection->insert($table, [
            'correlation_id' => $correlationId,
            'channel' => $channel,
            'action' => 'test',
            'subject_type' => $subjectType,
            'actor_type' => 'cron',
            'actor_name' => 'cron',
            'outcome' => 'success',
            'occurred_at_ms' => $occurredAtMs,
            'created_at' => gmdate('Y-m-d H:i:s', intdiv($occurredAtMs, 1000)),
        ]);

        $id = $connection->fetchOne(
            $connection->select()
                ->from($table, [ActivityResource::ID_FIELD])
                ->where('correlation_id = ?', $correlationId)
        );
        self::assertIsNumeric($id);

        return (int) $id;
    }

    /**
     * Which of the given ids are still in the journal, in the given order
     *
     * @param list<int> $ids
     * @return list<int>
     */
    private function remaining(array $ids): array
    {
        $resource = self::service(ResourceConnection::class);
        $connection = $resource->getConnection();
        $found = [];
        $rows = $connection->fetchCol(
            $connection->select()
                ->from($resource->getTableName(ActivityResource::TABLE), [ActivityResource::ID_FIELD])
                ->where(ActivityResource::ID_FIELD . ' IN (?)', $ids)
        );
        foreach ($rows as $row) {
            if (is_numeric($row)) {
                $found[] = (int) $row;
            }
        }

        return array_values(array_filter($ids, static fn (int $id): bool => in_array($id, $found, true)));
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
