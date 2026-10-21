<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Dashboard;

use Hryvinskyi\ActivityLog\Api\ChannelPoolInterface;
use Hryvinskyi\ActivityLog\Api\Data\DashboardQueryInterface;
use Hryvinskyi\ActivityLog\Api\Data\DashboardQueryInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\MillisecondClockInterface;
use Hryvinskyi\ActivityLog\Model\Data\DashboardQuery;

/**
 * Turns what the dashboard asked for into a valid query: an unknown period falls back to the saved one, then to
 * 30 days; unknown channels are dropped, and no channel left means every registered channel
 */
class QueryResolver
{
    public const DEFAULT_PERIOD = 30;

    /**
     * @param ChannelPoolInterface $channelPool
     * @param PreferenceStorageInterface $preferences
     * @param DashboardQueryInterfaceFactory $queryFactory
     * @param MillisecondClockInterface $clock
     */
    public function __construct(
        private readonly ChannelPoolInterface $channelPool,
        private readonly PreferenceStorageInterface $preferences,
        private readonly DashboardQueryInterfaceFactory $queryFactory,
        private readonly MillisecondClockInterface $clock
    ) {
    }

    /**
     * A query from request values, falling back to the defaults for anything not offered
     *
     * A period the dashboard offers, or null
     *
     * A query from request values, falling back to the defaults for anything not offered
     *
     * @param mixed $periodDays As requested, or null to use the saved choice
     * @param mixed $channels As requested, or null to use the saved choice
     * @return DashboardQueryInterface
     */
    public function resolve(mixed $periodDays, mixed $channels): DashboardQueryInterface
    {
        $saved = $this->preferences->load();

        return $this->queryFactory->create([
            'periodDays' => $this->period($periodDays) ?? $this->period($saved['periodDays']) ?? self::DEFAULT_PERIOD,
            'channels' => $this->channels($channels) ?? $this->channels($saved['channels']) ?? $this->allChannels(),
            'toMs' => $this->clock->nowMs(),
        ]);
    }

    /**
     * A period the dashboard offers, or null
     *
     * @param mixed $value
     * @return int|null
     */
    private function period(mixed $value): ?int
    {
        $days = is_numeric($value) ? (int) $value : null;

        return in_array($days, DashboardQuery::PERIODS, true) ? $days : null;
    }

    /**
     * Registered channels among those asked for, or null when none is left
     *
     * @param mixed $value
     * @return list<string>|null
     */
    private function channels(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $registered = $this->allChannels();
        $chosen = array_values(array_intersect($registered, array_filter($value, 'is_string')));

        return $chosen === [] ? null : $chosen;
    }

    /**
     * Every registered channel; a store with none yet still gets a valid query
     *
     * @return list<string>
     */
    private function allChannels(): array
    {
        $codes = array_map(static fn ($channel): string => $channel->getCode(), $this->channelPool->all());

        return $codes === [] ? ['none'] : $codes;
    }
}
