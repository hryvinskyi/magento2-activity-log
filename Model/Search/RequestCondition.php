<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterface;
use Hryvinskyi\ActivityLog\Model\Search\Query\SearchQueryBuilder;
use Hryvinskyi\ActivityLog\Model\Search\Query\TimeScope;

/**
 * A search request as one SQL condition on the journal, for a query that pages and sorts the matches its own way
 *
 * The condition holds everything the search engine applies to a result page — the range, "changes only", every
 * filter, and residual filters only among the newest capped candidates — so the rows it lets through are exactly the
 * rows the engine finds. The request's page position and page size are not part of it: the caller pages. When the
 * residual filters were matched among capped candidates only, a count of the rows is a lower bound.
 */
class RequestCondition
{
    /**
     * @param CriteriaResolver $criteriaResolver
     * @param SearchQueryBuilder $queryBuilder
     */
    public function __construct(
        private readonly CriteriaResolver $criteriaResolver,
        private readonly SearchQueryBuilder $queryBuilder
    ) {
    }

    /**
     * The request's condition on the journal known by that alias, ready to be one part of a WHERE clause
     *
     * @param SearchRequestInterface $request
     * @param string $alias
     * @return string
     */
    public function sql(SearchRequestInterface $request, string $alias): string
    {
        $conditions = $this->queryBuilder->conditions(
            $request,
            $this->criteriaResolver->resolve($request),
            TimeScope::InRange,
            false,
            $alias
        );

        return '((' . implode(') AND (', $conditions) . '))';
    }

    /**
     * Whether the request's residual filters had more candidates than they are matched among
     *
     * @param SearchRequestInterface $request
     * @return bool
     */
    public function isNarrowed(SearchRequestInterface $request): bool
    {
        return $this->queryBuilder->exceedsCandidateLimit(
            $request,
            $this->criteriaResolver->resolve($request),
            TimeScope::InRange,
            false
        );
    }
}
