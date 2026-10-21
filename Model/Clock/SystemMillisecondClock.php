<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Clock;

use Hryvinskyi\ActivityLog\Api\MillisecondClockInterface;

/**
 * The system clock
 */
class SystemMillisecondClock implements MillisecondClockInterface
{
    /**
     * @inheritDoc
     */
    public function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
