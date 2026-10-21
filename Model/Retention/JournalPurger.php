<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Retention;

use Hryvinskyi\ActivityLog\Api\ChannelPoolInterface;
use Hryvinskyi\ActivityLog\Api\ConfigInterface;
use Hryvinskyi\ActivityLog\Api\MillisecondClockInterface;
use Hryvinskyi\ActivityLog\Api\PurgerInterface;

/**
 * Applies each channel's retention, and the orphan retention to channels no installed module registers
 *
 * Channels whose module purges them itself (retention null) are left alone.
 */
class JournalPurger implements PurgerInterface
{
    private const DAY_MS = 86_400_000;

    /**
     * @param ChannelPoolInterface $channelPool
     * @param ConfigInterface $config
     * @param BatchDeleter $batchDeleter
     * @param MillisecondClockInterface $clock
     */
    public function __construct(
        private readonly ChannelPoolInterface $channelPool,
        private readonly ConfigInterface $config,
        private readonly BatchDeleter $batchDeleter,
        private readonly MillisecondClockInterface $clock
    ) {
    }

    /**
     * @inheritDoc
     */
    public function purge(): int
    {
        $removed = 0;
        $registered = [];
        foreach ($this->channelPool->all() as $channel) {
            $registered[] = $channel->getCode();
            $days = $channel->getRetentionDays();
            if ($days !== null) {
                $removed += $this->batchDeleter->deleteOlderThan(
                    $this->cutoff($days),
                    ['channel = ?' => $channel->getCode()]
                );
            }
        }

        $orphanCutoff = $this->cutoff($this->config->getOrphanRetentionDays());

        return $removed + $this->batchDeleter->deleteOlderThan(
            $orphanCutoff,
            $registered === [] ? [] : ['channel NOT IN (?)' => $registered]
        );
    }

    /**
     * The moment before which activities are past the given retention
     *
     * @param int $days
     * @return int
     */
    private function cutoff(int $days): int
    {
        return $this->clock->nowMs() - $days * self::DAY_MS;
    }
}
