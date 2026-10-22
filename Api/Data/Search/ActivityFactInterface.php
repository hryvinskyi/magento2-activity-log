<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data\Search;

/**
 * When an activity happened and who did it, as the journal recorded them
 *
 * Created blank through ActivityFactInterfaceFactory and filled through withActivity(); an instance is never changed.
 *
 * @api
 */
interface ActivityFactInterface
{
    /**
     * The activity's id in the journal
     *
     * @return int
     */
    public function getActivityId(): int;

    /**
     * When it happened, ms since the epoch
     *
     * @return int
     */
    public function getOccurredAtMs(): int;

    /**
     * The actor's name as recorded; empty when none was
     *
     * @return string
     */
    public function getActorName(): string;

    /**
     * A copy describing this activity
     *
     * @param int $activityId 1 or more
     * @param int $occurredAtMs 0 or more
     * @param string $actorName
     * @return ActivityFactInterface
     * @throws \InvalidArgumentException When the id or the time is out of range
     */
    public function withActivity(int $activityId, int $occurredAtMs, string $actorName): ActivityFactInterface;
}
