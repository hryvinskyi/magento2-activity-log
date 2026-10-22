<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data\Search;

/**
 * A number of matching activities, exact or a lower bound
 *
 * Counting stops at a bound so a count never scans the whole journal: past it the count says "at least" (shown as
 * "10,000+"), and a count taken where the value search had to stop early says "at least" too.
 *
 * @api
 */
interface SearchCountInterface
{
    /**
     * How many activities, or the lower bound when isAtLeast()
     *
     * @return int
     */
    public function getCount(): int;

    /**
     * Whether there may be more than getCount()
     *
     * @return bool
     */
    public function isAtLeast(): bool;
}
