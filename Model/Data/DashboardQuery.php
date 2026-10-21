<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data;

use Hryvinskyi\ActivityLog\Api\Data\DashboardQueryInterface;

/**
 * Immutable dashboard query, created through DashboardQueryInterfaceFactory
 */
class DashboardQuery implements DashboardQueryInterface
{
    public const PERIODS = [7, 14, 30];
    private const DAY_MS = 86_400_000;

    /**
     * @param int $periodDays
     * @param list<string> $channels
     * @param int $toMs
     * @throws \InvalidArgumentException When the period or a channel code is not allowed
     */
    public function __construct(
        private readonly int $periodDays,
        private readonly array $channels,
        private readonly int $toMs
    ) {
        if (!in_array($periodDays, self::PERIODS, true)) {
            throw new \InvalidArgumentException(
                sprintf('A dashboard period is 7, 14 or 30 days, not %d.', $periodDays)
            );
        }

        if ($channels === []) {
            throw new \InvalidArgumentException('A dashboard shows at least one channel.');
        }

        foreach ($channels as $channel) {
            if (preg_match(Activity::CHANNEL_PATTERN, $channel) !== 1) {
                throw new \InvalidArgumentException(sprintf('Malformed channel code "%s".', $channel));
            }
        }

        if ($toMs <= 0) {
            throw new \InvalidArgumentException('The end of a dashboard period is a positive number of milliseconds.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getPeriodDays(): int
    {
        return $this->periodDays;
    }

    /**
     * @inheritDoc
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * @inheritDoc
     */
    public function getFromMs(): int
    {
        return $this->toMs - $this->periodDays * self::DAY_MS;
    }

    /**
     * @inheritDoc
     */
    public function getToMs(): int
    {
        return $this->toMs;
    }

    /**
     * @inheritDoc
     */
    public function getPreviousRange(): array
    {
        return ['fromMs' => $this->getFromMs() - $this->periodDays * self::DAY_MS, 'toMs' => $this->getFromMs()];
    }
}
