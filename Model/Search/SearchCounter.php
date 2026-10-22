<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCountInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCountInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterface;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Hryvinskyi\ActivityLog\Model\Search\Query\ResolvedCriterion;
use Hryvinskyi\ActivityLog\Model\Search\Query\SearchQueryBuilder;
use Hryvinskyi\ActivityLog\Model\Search\Query\TimeScope;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * The counts a result page shows, each read by one query that stops at BOUND matches
 *
 * Only the time range — with "changes only", an indexed channel list — is counted exactly: that count reads the time
 * index alone. Any filter makes the count stop at BOUND ("10,000+"). A count whose value search stopped at the
 * candidate limit is a lower bound too. Two bounded counts are never subtracted: "outside the range" is its own query
 * with the range negated.
 */
class SearchCounter
{
    /**
     * Matches counted before a count says "at least"
     */
    public const BOUND = 10000;

    /**
     * @param SearchQueryBuilder $queryBuilder
     * @param SearchCountInterfaceFactory $countFactory
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly SearchQueryBuilder $queryBuilder,
        private readonly SearchCountInterfaceFactory $countFactory,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Matches in the request's range
     *
     * @param SearchRequestInterface $request
     * @param list<ResolvedCriterion> $criteria
     * @return SearchCountInterface
     */
    public function inRange(SearchRequestInterface $request, array $criteria): SearchCountInterface
    {
        if ($criteria === []) {
            return $this->count(
                $this->queryBuilder->countOf($this->queryBuilder->matches($request, [], TimeScope::InRange, false)),
                false
            );
        }

        return $this->bounded($request, $criteria, TimeScope::InRange);
    }

    /**
     * Matches outside the request's range
     *
     * @param SearchRequestInterface $request
     * @param list<ResolvedCriterion> $criteria
     * @return SearchCountInterface
     */
    public function outside(SearchRequestInterface $request, array $criteria): SearchCountInterface
    {
        return $this->bounded($request, $criteria, TimeScope::Outside);
    }

    /**
     * Time of the newest match outside the request's range, or null when there is none
     *
     * @param SearchRequestInterface $request
     * @param list<ResolvedCriterion> $criteria
     * @return int|null
     */
    public function latestOutsideMs(SearchRequestInterface $request, array $criteria): ?int
    {
        $select = $this->queryBuilder->matches($request, $criteria, TimeScope::Outside, false)
            ->reset(Select::COLUMNS)
            ->columns(['occurred_at_ms' => SearchQueryBuilder::ALIAS . '.occurred_at_ms'])
            ->order(SearchQueryBuilder::ALIAS . '.occurred_at_ms DESC')
            ->limit(1);
        $latest = $this->resourceConnection->getConnection()->fetchOne($select);

        return is_numeric($latest) ? (int) $latest : null;
    }

    /**
     * Two counts of disjoint matches together, still stopping at the bound
     *
     * @param SearchCountInterface $first
     * @param SearchCountInterface $second
     * @return SearchCountInterface
     */
    public function sum(SearchCountInterface $first, SearchCountInterface $second): SearchCountInterface
    {
        $sum = $first->getCount() + $second->getCount();
        $atLeast = $first->isAtLeast() || $second->isAtLeast() || $sum > self::BOUND;

        return $this->count($atLeast ? min($sum, self::BOUND) : $sum, $atLeast);
    }

    /**
     * Whether the journal holds no activity at all
     *
     * @return bool
     */
    public function isJournalEmpty(): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName(ActivityResource::TABLE), ['entity_id'])
            ->limit(1);

        return !is_numeric($connection->fetchOne($select));
    }

    /**
     * Matches on one side of the range, counted up to the bound
     *
     * @param SearchRequestInterface $request
     * @param list<ResolvedCriterion> $criteria
     * @param TimeScope $scope
     * @return SearchCountInterface
     */
    private function bounded(SearchRequestInterface $request, array $criteria, TimeScope $scope): SearchCountInterface
    {
        $count = $this->queryBuilder->countOf(
            $this->queryBuilder->matches($request, $criteria, $scope, false)->limit(self::BOUND + 1)
        );
        $atLeast = $count > self::BOUND
            || $this->queryBuilder->exceedsCandidateLimit($request, $criteria, $scope, false);

        return $this->count(min($count, self::BOUND), $atLeast);
    }

    /**
     * A count value
     *
     * @param int $count
     * @param bool $atLeast
     * @return SearchCountInterface
     */
    private function count(int $count, bool $atLeast): SearchCountInterface
    {
        return $this->countFactory->create(['count' => $count, 'atLeast' => $atLeast]);
    }
}
