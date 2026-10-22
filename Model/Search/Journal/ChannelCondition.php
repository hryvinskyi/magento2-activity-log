<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Journal;

use Hryvinskyi\ActivityLog\Api\ChannelPoolInterface;
use Hryvinskyi\ActivityLog\Model\Data\Activity;
use Magento\Framework\App\ResourceConnection;

/**
 * "channel": a registered channel by code or label, on the indexed channel column
 *
 * A code no installed module registers still finds the rows its module left in the journal.
 */
class ChannelCondition implements JournalConditionInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param ChannelPoolInterface $channelPool
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ChannelPoolInterface $channelPool
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return 'channel';
    }

    /**
     * @inheritDoc
     */
    public function isResidual(string $value): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function condition(string $value, string $alias): ?string
    {
        $wanted = mb_strtolower($value);
        $codes = [];
        foreach ($this->channelPool->all() as $channel) {
            $label = $channel->getLabel();
            if ($channel->getCode() === $wanted
                || mb_strtolower($label) === $wanted
                || mb_strtolower((string) __($label)) === $wanted
            ) {
                $codes[] = $channel->getCode();
            }
        }

        if ($codes === [] && preg_match(Activity::CHANNEL_PATTERN, $wanted) === 1) {
            $codes[] = $wanted;
        }

        return $codes === []
            ? null
            : $this->resourceConnection->getConnection()->quoteInto($alias . '.channel IN (?)', $codes);
    }
}
