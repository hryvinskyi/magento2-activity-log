<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

/**
 * Current time with millisecond precision, so activities of one operation keep their order and offsets
 *
 * @api
 */
interface MillisecondClockInterface
{
    /**
     * Milliseconds since the Unix epoch
     *
     * @return int
     */
    public function nowMs(): int;
}
