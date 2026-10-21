<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Source;

use Hryvinskyi\ActivityLog\Api\ChannelPoolInterface;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * The registered channels, for filters
 */
class ChannelOptions implements OptionSourceInterface
{
    /**
     * @param ChannelPoolInterface $channelPool
     */
    public function __construct(
        private readonly ChannelPoolInterface $channelPool
    ) {
    }

    /**
     * @inheritDoc
     *
     * @return list<array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->channelPool->all() as $channel) {
            $options[] = ['value' => $channel->getCode(), 'label' => __($channel->getLabel())];
        }

        return $options;
    }
}
