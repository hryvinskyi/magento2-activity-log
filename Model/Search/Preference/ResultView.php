<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Preference;

/**
 * How the search page shows its results: the same search, as a timeline or as the grid
 */
enum ResultView: string
{
    /** One sentence per activity, grouped by day */
    case Timeline = 'timeline';

    /** The activity grid, with its columns, paging, saved views and export */
    case Grid = 'grid';
}
