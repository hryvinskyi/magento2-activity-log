<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Channel;

use Hryvinskyi\ActivityLog\Api\ChannelPoolInterface;

/**
 * The registered channels as the browser code reads them: translated label and colours by code
 */
class ChannelPresenter
{
    /**
     * @param ChannelPoolInterface $channelPool
     */
    public function __construct(
        private readonly ChannelPoolInterface $channelPool
    ) {
    }

    /**
     * Channels keyed by code
     *
     * @return array<string, array{label: string, chart: string, bg: string, fg: string}>
     */
    public function present(): array
    {
        $channels = [];
        foreach ($this->channelPool->all() as $channel) {
            $channels[$channel->getCode()] = [
                'label' => (string) __($channel->getLabel()),
                'chart' => $channel->getChartColor(),
                'bg' => $channel->getBadgeBackground(),
                'fg' => $channel->getBadgeForeground(),
            ];
        }

        return $channels;
    }
}
