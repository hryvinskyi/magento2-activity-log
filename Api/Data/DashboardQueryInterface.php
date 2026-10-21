<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data;

/**
 * What the dashboard is showing: a period ending now and the channels switched on
 *
 * @api
 */
interface DashboardQueryInterface
{
    /**
     * Length of the period in days: 7, 14 or 30
     *
     * @return int
     */
    public function getPeriodDays(): int;

    /**
     * Channel codes to include; never empty
     *
     * @return list<string>
     */
    public function getChannels(): array;

    /**
     * Start of the period, ms since the epoch (inclusive)
     *
     * @return int
     */
    public function getFromMs(): int;

    /**
     * End of the period, ms since the epoch (exclusive)
     *
     * @return int
     */
    public function getToMs(): int;

    /**
     * The period of the same length just before this one, for comparisons
     *
     * @return array{fromMs: int, toMs: int}
     */
    public function getPreviousRange(): array;
}
