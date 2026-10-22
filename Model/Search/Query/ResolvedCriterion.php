<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Query;

/**
 * One filter turned into SQL alternatives, ready to be placed on the journal under any alias
 *
 * Each alternative renders a condition for an alias, or null when it can match nothing. The filter matches a row
 * matching any alternative, and nothing when no alternative is left. It is residual when any alternative is: an OR
 * cannot be split between the narrowing and the residual phase. A residual filter is rendered for rows already
 * among the capped candidates, which an alternative may use to tie its own sub-select to the row it is asked about:
 * inside an OR the database cannot join a sub-select and would otherwise read it whole.
 */
class ResolvedCriterion
{
    /**
     * @param bool $residual
     * @param list<\Closure(string,bool):?string> $alternatives
     */
    public function __construct(
        private readonly bool $residual,
        private readonly array $alternatives
    ) {
    }

    /**
     * Whether the filter may only be applied among the capped candidates
     *
     * @return bool
     */
    public function isResidual(): bool
    {
        return $this->residual;
    }

    /**
     * The filter as one SQL condition on the journal known by that alias
     *
     * @param string $alias
     * @param bool $amongCandidates Whether the rows are already limited to the capped candidates (a residual filter)
     * @return string
     */
    public function render(string $alias, bool $amongCandidates = false): string
    {
        $conditions = [];
        foreach ($this->alternatives as $alternative) {
            $condition = $alternative($alias, $amongCandidates);
            if ($condition !== null) {
                $conditions[] = '(' . $condition . ')';
            }
        }

        return $conditions === [] ? '1 = 0' : '(' . implode(' OR ', $conditions) . ')';
    }
}
