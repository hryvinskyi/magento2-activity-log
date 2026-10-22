<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Search;

/**
 * Who acted in a set of activities, as the journal recorded them, for a module that knows which activities concern
 * something it owns (the changes of one record) and offers to narrow them by actor
 *
 * @api
 */
interface ActivityActorsInterface
{
    /**
     * Most activities one call may name
     */
    public const MAX_ACTIVITIES = 1000;

    /**
     * Most names one call returns
     */
    public const MAX_NAMES = 200;

    /**
     * The distinct actor names of these activities, in name order, at most MAX_NAMES; blank names are left out
     *
     * @param list<int> $activityIds At most MAX_ACTIVITIES
     * @return list<string>
     * @throws \InvalidArgumentException When more than MAX_ACTIVITIES activities are named
     */
    public function namesOf(array $activityIds): array;
}
