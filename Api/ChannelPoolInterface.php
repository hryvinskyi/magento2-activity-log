<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

/**
 * The channels registered by installed modules
 *
 * @api
 */
interface ChannelPoolInterface
{
    /**
     * Channel with that code, or null when no installed module registers it
     *
     * @param string $code
     * @return ChannelInterface|null
     */
    public function get(string $code): ?ChannelInterface;

    /**
     * Every registered channel, in registration order
     *
     * @return list<ChannelInterface>
     */
    public function all(): array;
}
