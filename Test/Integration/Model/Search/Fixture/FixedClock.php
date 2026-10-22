<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture;

use Hryvinskyi\ActivityLog\Api\MillisecondClockInterface;

/**
 * A clock stopped at a given instant, so that "recent" means the same thing whenever a test runs
 */
class FixedClock implements MillisecondClockInterface
{
    /**
     * @param int $nowMs
     */
    public function __construct(private readonly int $nowMs)
    {
    }

    /**
     * @inheritDoc
     */
    public function nowMs(): int
    {
        return $this->nowMs;
    }
}
