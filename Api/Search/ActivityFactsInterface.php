<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\ActivityFactInterface;

/**
 * When given activities happened and who did them, read in one go, for a module that knows which activities concern
 * something it owns (the newest change of each setting) and shows when and by whom
 *
 * @api
 */
interface ActivityFactsInterface
{
    /**
     * Most activities one call may name
     */
    public const MAX_ACTIVITIES = 1000;

    /**
     * The facts of the activities the journal still holds, keyed by activity id; the others are left out
     *
     * @param list<int> $activityIds At most MAX_ACTIVITIES
     * @return array<int, ActivityFactInterface>
     * @throws \InvalidArgumentException When more than MAX_ACTIVITIES activities are named
     */
    public function of(array $activityIds): array;
}
