<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data\Search;

/**
 * A time range relative to now, which the browser turns into milliseconds in the store's timezone
 *
 * "Last N days" runs from local midnight N−1 days ago to now.
 *
 * @api
 */
enum RangePreset: string
{
    case LastHour = 'hour';
    case Today = 'today';
    case Yesterday = 'yesterday';
    case LastSevenDays = '7d';
    case LastThirtyDays = '30d';
}
