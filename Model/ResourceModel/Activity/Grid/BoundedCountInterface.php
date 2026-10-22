<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\ResourceModel\Activity\Grid;

/**
 * A result that can count its rows without reading past a bound
 */
interface BoundedCountInterface
{
    /**
     * Rows matching the result's filters, counted up to one more than the bound
     *
     * A count above the bound means "more than the bound"; the rows beyond it are never read.
     *
     * @param int $bound Zero or more
     * @return int At most $bound + 1
     */
    public function countUpTo(int $bound): int;
}
