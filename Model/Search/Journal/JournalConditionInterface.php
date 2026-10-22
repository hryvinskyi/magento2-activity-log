<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Journal;

/**
 * A search key answered from the journal's own columns, as a condition on the journal table under any alias
 *
 * Internal to the journal: other modules contribute through SearchConditionInterface, whose sub-selects keep them out
 * of the journal query.
 */
interface JournalConditionInterface
{
    /**
     * The key served, such as "by"
     *
     * @return string
     */
    public function getKey(): string;

    /**
     * Whether this value is matched outside any index, and so only among the newest candidates
     *
     * @param string $value
     * @return bool
     */
    public function isResidual(string $value): bool;

    /**
     * SQL condition on the journal table known by that alias, every value quoted; null when the value can match no row
     *
     * @param string $value Trimmed, as the person typed it
     * @param string $alias
     * @return string|null
     */
    public function condition(string $value, string $alias): ?string;
}
