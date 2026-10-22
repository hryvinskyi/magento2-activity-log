<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * "The last N days" as the search page counts them: from local midnight N−1 days ago, in the store's timezone, to now
 *
 * The browser's range presets apply the same rule, so a count the server makes for "the last 30 days" and the list
 * the page shows for that preset hold the same activities. A local midnight that does not exist on a daylight-saving
 * day moves forward to the first instant that does.
 *
 * @api
 */
class RecentDays
{
    /**
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * Start of the last N days ending at that moment, ms since the epoch
     *
     * @param int $days One or more; 1 is today
     * @param int $nowMs The moment the range ends, ms since the epoch
     * @return int
     * @throws \InvalidArgumentException When the number of days is below one
     */
    public function startMs(int $days, int $nowMs): int
    {
        if ($days < 1) {
            throw new \InvalidArgumentException(sprintf('"The last %d days" is not a range.', $days));
        }

        $local = $this->timezone->date(intdiv($nowMs, 1000), null, true);
        $start = $local
            ->setDate((int) $local->format('Y'), (int) $local->format('n'), (int) $local->format('j') - ($days - 1))
            ->setTime(0, 0);

        return $start->getTimestamp() * 1000;
    }
}
