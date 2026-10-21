<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Channel;

use Hryvinskyi\ActivityLog\Api\ChannelInterface;
use Hryvinskyi\ActivityLog\Api\ChannelPoolInterface;

/**
 * Channels registered through the "channels" argument
 */
class ChannelPool implements ChannelPoolInterface
{
    /**
     * @var array<string, ChannelInterface>
     */
    private array $byCode = [];

    /**
     * @param array<string,ChannelInterface> $channels
     */
    public function __construct(array $channels = [])
    {
        foreach ($channels as $channel) {
            $this->byCode[$channel->getCode()] = $channel;
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $code): ?ChannelInterface
    {
        return $this->byCode[$code] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return array_values($this->byCode);
    }
}
