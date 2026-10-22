<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Query;

/**
 * Which side of a request's time range a query looks at
 */
enum TimeScope
{
    /** Activities within the range, both ends included */
    case InRange;

    /** Activities before or after the range */
    case Outside;
}
