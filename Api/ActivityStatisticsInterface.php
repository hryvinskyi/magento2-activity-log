<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

use Hryvinskyi\ActivityLog\Api\Data\DashboardQueryInterface;

/**
 * Figures about the journal for a dashboard period, for the dashboard's widgets and those channel modules add
 *
 * @api
 */
interface ActivityStatisticsInterface
{
    /**
     * Totals of a range
     *
     * @param list<string> $channels
     * @param int $fromMs
     * @param int $toMs
     * @return array{total: int, avgDurationMs: float|null, failures: int, denied: int}
     */
    public function totals(array $channels, int $fromMs, int $toMs): array;

    /**
     * Activities per day of the store timezone, per channel, for every day of the period
     *
     * @param DashboardQueryInterface $query
     * @return array{days: list<string>, counts: array<string, list<int>>}
     */
    public function volumeByDay(DashboardQueryInterface $query): array;

    /**
     * Actions with the highest average duration, slowest first
     *
     * @param DashboardQueryInterface $query
     * @param int $limit
     * @return list<array{action: string, channel: string, avgDurationMs: float, count: int}>
     */
    public function slowestActions(DashboardQueryInterface $query, int $limit): array;

    /**
     * Most frequent actions, optionally of one outcome
     *
     * @param DashboardQueryInterface $query
     * @param int $limit
     * @param string|null $outcome
     * @return list<array{action: string, channel: string, count: int}>
     */
    public function topActions(DashboardQueryInterface $query, int $limit, ?string $outcome = null): array;

    /**
     * Actors with the most activities; a delegating client makes a separate row from its account
     *
     * @param DashboardQueryInterface $query
     * @param int $limit
     * @return list<array{actorName: string, actorType: string, viaClient: string|null, viaChannel: string|null,
     *     count: int, failures: int}>
     */
    public function topActors(DashboardQueryInterface $query, int $limit): array;
}
