<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCursorInterface;

/**
 * Immutable page position, created at (0, 0) through SearchCursorInterfaceFactory and placed through withPosition()
 */
class SearchCursor implements SearchCursorInterface
{
    /**
     * @var int
     */
    private int $occurredAtMs = 0;

    /**
     * @var int
     */
    private int $activityId = 0;

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
    public function getActivityId(): int
    {
        return $this->activityId;
    }

    /**
     * @inheritDoc
     */
    public function withPosition(int $occurredAtMs, int $activityId): SearchCursorInterface
    {
        if ($occurredAtMs < 0 || $activityId < 1) {
            throw new \InvalidArgumentException(
                sprintf(
                    'A page position is a time of 0 or more and an id of 1 or more, not (%d, %d).',
                    $occurredAtMs,
                    $activityId
                )
            );
        }

        $copy = clone $this;
        $copy->occurredAtMs = $occurredAtMs;
        $copy->activityId = $activityId;

        return $copy;
    }
}
