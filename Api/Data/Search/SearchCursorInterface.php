<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data\Search;

/**
 * Where a page of search results ended: the time and id of its last activity
 *
 * The next page holds the activities strictly older than this position, newest first, with the id breaking ties
 * between activities of the same millisecond. Create one through SearchCursorInterfaceFactory and place it with
 * withPosition(); a cursor straight from the factory is at (0, 0), which no search request accepts.
 *
 * @api
 */
interface SearchCursorInterface
{
    /**
     * Time of the last activity shown, ms since the epoch
     *
     * @return int
     */
    public function getOccurredAtMs(): int;

    /**
     * Id of the last activity shown
     *
     * @return int
     */
    public function getActivityId(): int;

    /**
     * A copy at this position; this cursor is left as it is
     *
     * @param int $occurredAtMs Zero or more
     * @param int $activityId One or more
     * @return SearchCursorInterface
     * @throws \InvalidArgumentException When either number is out of range
     */
    public function withPosition(int $occurredAtMs, int $activityId): SearchCursorInterface;
}
