<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\ActivityFactInterface;

/**
 * Immutable facts of one activity, created blank through ActivityFactInterfaceFactory and filled through
 * withActivity()
 */
class ActivityFact implements ActivityFactInterface
{
    /**
     * @var int
     */
    private int $activityId = 0;

    /**
     * @var int
     */
    private int $occurredAtMs = 0;

    /**
     * @var string
     */
    private string $actorName = '';

    /**
     * @inheritDoc
     */
    public function getActivityId(): int
    {
        return $this->activityId;
    }

    /**
     * @inheritDoc
     */
    public function getOccurredAtMs(): int
    {
        return $this->occurredAtMs;
    }

    /**
     * @inheritDoc
     */
    public function getActorName(): string
    {
        return $this->actorName;
    }

    /**
     * @inheritDoc
     */
    public function withActivity(int $activityId, int $occurredAtMs, string $actorName): ActivityFactInterface
    {
        if ($activityId < 1 || $occurredAtMs < 0) {
            throw new \InvalidArgumentException(sprintf(
                'An activity has an id of 1 or more and a time of 0 or more, not %d and %d.',
                $activityId,
                $occurredAtMs
            ));
        }

        $copy = clone $this;
        $copy->activityId = $activityId;
        $copy->occurredAtMs = $occurredAtMs;
        $copy->actorName = $actorName;

        return $copy;
    }
}
