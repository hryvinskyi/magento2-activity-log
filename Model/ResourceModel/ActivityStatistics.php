<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\ResourceModel;

use Hryvinskyi\ActivityLog\Api\ActivityStatisticsInterface;
use Hryvinskyi\ActivityLog\Api\Data\DashboardQueryInterface;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\DB\Sql\ExpressionFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Journal figures computed in SQL on the indexed time and channel columns
 *
 * Days are the store timezone's: activity is counted per UTC hour in SQL and each hour is placed on its local day
 * here, which keeps the days right across daylight-saving changes.
 */
class ActivityStatistics implements ActivityStatisticsInterface
{
    private const HOUR_MS = 3_600_000;

    /**
     * @param ResourceConnection $resourceConnection
     * @param TimezoneInterface $timezone
     * @param ExpressionFactory $expressionFactory
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly TimezoneInterface $timezone,
        private readonly ExpressionFactory $expressionFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function totals(array $channels, int $fromMs, int $toMs): array
    {
        $select = $this->range($channels, $fromMs, $toMs)->columns([
            'total' => $this->expr('COUNT(*)'),
            'avg_duration' => $this->expr('AVG(duration_ms)'),
            'failures' => $this->expr("SUM(outcome = 'failure')"),
            'denied' => $this->expr("SUM(outcome = 'denied')"),
        ]);
        $row = $this->connection()->fetchRow($select);
        $row = is_array($row) ? $row : [];

        return [
            'total' => $this->int($row['total'] ?? 0),
            'avgDurationMs' => is_numeric($row['avg_duration'] ?? null) ? (float) $row['avg_duration'] : null,
            'failures' => $this->int($row['failures'] ?? 0),
            'denied' => $this->int($row['denied'] ?? 0),
        ];
    }

    /**
     * @inheritDoc
     */
    public function volumeByDay(DashboardQueryInterface $query): array
    {
        $days = [];
        $toMs = $query->getToMs();
        for ($hourMs = $query->getFromMs(); $hourMs < $toMs; $hourMs += self::HOUR_MS) {
            $days[$this->localDay($hourMs)] = true;
        }
        $days = array_keys($days);
        $dayIndex = array_flip($days);

        $counts = [];
        foreach ($query->getChannels() as $channel) {
            $counts[$channel] = array_fill(0, count($days), 0);
        }

        $select = $this->range($query->getChannels(), $query->getFromMs(), $query->getToMs())
            ->columns([
                'channel' => 'channel',
                'hour' => $this->expr('FLOOR(occurred_at_ms / ' . self::HOUR_MS . ')'),
                'total' => $this->expr('COUNT(*)'),
            ])
            ->group(['channel', 'hour']);

        foreach ($this->connection()->fetchAll($select) as $row) {
            if (!is_array($row) || !is_string($row['channel'] ?? null) || !isset($counts[$row['channel']])) {
                continue;
            }

            $day = $this->localDay($this->int($row['hour'] ?? 0) * self::HOUR_MS);
            if (isset($dayIndex[$day])) {
                $counts[$row['channel']][$dayIndex[$day]] += $this->int($row['total'] ?? 0);
            }
        }

        return ['days' => $days, 'counts' => $counts];
    }

    /**
     * @inheritDoc
     */
    public function slowestActions(DashboardQueryInterface $query, int $limit): array
    {
        $select = $this->range($query->getChannels(), $query->getFromMs(), $query->getToMs())
            ->columns([
                'action' => 'action',
                'channel' => $this->expr('MIN(channel)'),
                'avg_duration' => $this->expr('AVG(duration_ms)'),
                'total' => $this->expr('COUNT(*)'),
            ])
            ->where('duration_ms IS NOT NULL')
            ->group('action')
            ->order('avg_duration DESC')
            ->limit($limit);

        $actions = [];
        foreach ($this->connection()->fetchAll($select) as $row) {
            if (is_array($row) && is_string($row['action'] ?? null)) {
                $actions[] = [
                    'action' => $row['action'],
                    'channel' => is_string($row['channel'] ?? null) ? $row['channel'] : '',
                    'avgDurationMs' => is_numeric($row['avg_duration'] ?? null) ? (float) $row['avg_duration'] : 0.0,
                    'count' => $this->int($row['total'] ?? 0),
                ];
            }
        }

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function topActions(DashboardQueryInterface $query, int $limit, ?string $outcome = null): array
    {
        $select = $this->range($query->getChannels(), $query->getFromMs(), $query->getToMs())
            ->columns([
                'action' => 'action',
                'channel' => $this->expr('MIN(channel)'),
                'total' => $this->expr('COUNT(*)'),
            ])
            ->group('action')
            ->order('total DESC')
            ->limit($limit);
        if ($outcome !== null) {
            $select->where('outcome = ?', $outcome);
        }

        $actions = [];
        foreach ($this->connection()->fetchAll($select) as $row) {
            if (is_array($row) && is_string($row['action'] ?? null)) {
                $actions[] = [
                    'action' => $row['action'],
                    'channel' => is_string($row['channel'] ?? null) ? $row['channel'] : '',
                    'count' => $this->int($row['total'] ?? 0),
                ];
            }
        }

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function topActors(DashboardQueryInterface $query, int $limit): array
    {
        $select = $this->range($query->getChannels(), $query->getFromMs(), $query->getToMs())
            ->columns([
                'actor_name' => 'actor_name',
                'actor_type' => 'actor_type',
                'via_client' => 'via_client',
                'via_channel' => $this->expr('MIN(via_channel)'),
                'total' => $this->expr('COUNT(*)'),
                'failures' => $this->expr("SUM(outcome IN ('failure', 'denied'))"),
            ])
            ->group(['actor_type', 'actor_name', 'via_client'])
            ->order('total DESC')
            ->limit($limit);

        $actors = [];
        foreach ($this->connection()->fetchAll($select) as $row) {
            if (is_array($row) && is_string($row['actor_name'] ?? null)) {
                $actors[] = [
                    'actorName' => $row['actor_name'],
                    'actorType' => is_string($row['actor_type'] ?? null) ? $row['actor_type'] : '',
                    'viaClient' => is_string($row['via_client'] ?? null) ? $row['via_client'] : null,
                    'viaChannel' => is_string($row['via_channel'] ?? null) ? $row['via_channel'] : null,
                    'count' => $this->int($row['total'] ?? 0),
                    'failures' => $this->int($row['failures'] ?? 0),
                ];
            }
        }

        return $actors;
    }

    /**
     * Journal rows of the channels in a time range, with no columns chosen yet
     *
     * @param list<string> $channels
     * @param int $fromMs
     * @param int $toMs
     * @return Select
     */
    private function range(array $channels, int $fromMs, int $toMs): Select
    {
        return $this->connection()->select()
            ->from($this->resourceConnection->getTableName(ActivityResource::TABLE), [])
            ->where('occurred_at_ms >= ?', $fromMs)
            ->where('occurred_at_ms < ?', $toMs)
            ->where('channel IN (?)', $channels === [] ? [''] : $channels);
    }

    /**
     * The store-timezone day a moment falls on, "2026-10-20"
     *
     * @param int $ms
     * @return string
     */
    private function localDay(int $ms): string
    {
        return $this->timezone->date(intdiv($ms, 1000), null, true, true)->format('Y-m-d');
    }

    /**
     * An SQL expression column
     *
     * @param string $sql
     * @return Expression
     */
    private function expr(string $sql): Expression
    {
        return $this->expressionFactory->create(['expression' => $sql]);
    }

    /**
     * A database value as an integer
     *
     * @param mixed $value
     * @return int
     */
    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * The journal connection
     *
     * @return AdapterInterface
     */
    private function connection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
