<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCountInterface;

/**
 * Immutable count, created by the search through SearchCountInterfaceFactory from numbers it computed
 */
class SearchCount implements SearchCountInterface
{
    /**
     * @param int $count
     * @param bool $atLeast
     * @throws \InvalidArgumentException When the count is negative
     */
    public function __construct(
        private readonly int $count,
        private readonly bool $atLeast = false
    ) {
        if ($count < 0) {
            throw new \InvalidArgumentException(sprintf('A count is 0 or more, not %d.', $count));
        }
    }

    /**
     * @inheritDoc
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @inheritDoc
     */
    public function isAtLeast(): bool
    {
        return $this->atLeast;
    }
}
