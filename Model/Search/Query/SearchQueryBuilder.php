<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Query;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterface;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Hryvinskyi\ActivityLog\Model\Search\ChangeChannels;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;

/**
 * The journal query of a search, in two phases
 *
 * Narrowing conditions — the time range, "changes only", the page position and every filter on an indexed column —
 * select the candidates. Residual conditions (text matched outside indexes) are applied only to the newest
 * CANDIDATE_LIMIT candidates, which a derived table holds: the order of WHERE clauses decides nothing about what the
 * database reads first, and neither engine accepts a LIMIT inside "IN (…)" directly. A query without residual
 * conditions has no candidate limit.
 *
 * Conditions are added as finished SQL (Select::TYPE_CONDITION): Magento's where() would otherwise run quoteInto()
 * over them and replace every "?" a person typed inside a quoted value.
 */
class SearchQueryBuilder
{
    /**
     * Candidates a residual condition is matched among, newest first
     */
    public const CANDIDATE_LIMIT = 10000;

    public const ALIAS = 'main_table';
    private const CANDIDATE_ALIAS = 'candidate';

    /**
     * @param ResourceConnection $resourceConnection
     * @param ChangeChannels $changeChannels
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ChangeChannels $changeChannels
    ) {
    }

    /**
     * Matching journal rows (entity_id, occurred_at_ms, channel) under the alias "main_table", unordered and unlimited
     *
     * @param SearchRequestInterface $request
     * @param list<ResolvedCriterion> $criteria
     * @param TimeScope $scope
     * @param bool $afterCursor Whether to start after the request's page position
     * @return Select
     */
    public function matches(
        SearchRequestInterface $request,
        array $criteria,
        TimeScope $scope,
        bool $afterCursor
    ): Select {
        $select = $this->connection()->select()->from(
            [self::ALIAS => $this->table()],
            ['entity_id', 'occurred_at_ms', 'channel']
        );

        return $this->where($select, $this->conditions($request, $criteria, $scope, $afterCursor, self::ALIAS));
    }

    /**
     * Every condition a matching row satisfies, as finished SQL on the journal known by that alias
     *
     * Without residual filters these are the narrowing conditions. With them, the row must also be among the newest
     * CANDIDATE_LIMIT candidates (selected under an alias of their own) and satisfy each residual filter.
     *
     * @param SearchRequestInterface $request
     * @param list<ResolvedCriterion> $criteria
     * @param TimeScope $scope
     * @param bool $afterCursor Whether to start after the request's page position
     * @param string $alias Alias of the journal in the query the conditions go into
     * @return list<string>
     */
    public function conditions(
        SearchRequestInterface $request,
        array $criteria,
        TimeScope $scope,
        bool $afterCursor,
        string $alias
    ): array {
        $residual = $this->residual($criteria);
        if ($residual === []) {
            return $this->narrowing($alias, $request, $criteria, $scope, $afterCursor);
        }

        $candidates = $this->where(
            $this->connection()->select()->from([self::CANDIDATE_ALIAS => $this->table()], ['entity_id']),
            $this->narrowing(self::CANDIDATE_ALIAS, $request, $criteria, $scope, $afterCursor)
        )
            ->order([self::CANDIDATE_ALIAS . '.occurred_at_ms DESC', self::CANDIDATE_ALIAS . '.entity_id DESC'])
            ->limit(self::CANDIDATE_LIMIT);
        $candidateIds = $this->connection()->select()->from(['candidates' => $candidates], ['entity_id']);

        $conditions = [$alias . '.entity_id IN (' . $candidateIds->assemble() . ')'];
        foreach ($residual as $criterion) {
            $conditions[] = $criterion->render($alias, true);
        }

        return $conditions;
    }

    /**
     * Whether residual conditions had more candidates than they are matched among
     *
     * @param SearchRequestInterface $request
     * @param list<ResolvedCriterion> $criteria
     * @param TimeScope $scope
     * @param bool $afterCursor
     * @return bool
     */
    public function exceedsCandidateLimit(
        SearchRequestInterface $request,
        array $criteria,
        TimeScope $scope,
        bool $afterCursor
    ): bool {
        if ($this->residual($criteria) === []) {
            return false;
        }

        $candidates = $this->where(
            $this->connection()->select()->from([self::CANDIDATE_ALIAS => $this->table()], ['entity_id']),
            $this->narrowing(self::CANDIDATE_ALIAS, $request, $criteria, $scope, $afterCursor)
        )->limit(self::CANDIDATE_LIMIT + 1);

        return $this->countOf($candidates) > self::CANDIDATE_LIMIT;
    }

    /**
     * Number of rows a query returns
     *
     * @param Select $select
     * @return int
     */
    public function countOf(Select $select): int
    {
        $count = $this->connection()->fetchOne(
            $this->connection()->select()->from(['counted' => $select], ['COUNT(*)'])
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * Narrowing conditions on the journal known by that alias: range, "changes only", position, indexed filters
     *
     * @param string $alias
     * @param SearchRequestInterface $request
     * @param list<ResolvedCriterion> $criteria
     * @param TimeScope $scope
     * @param bool $afterCursor
     * @return list<string>
     */
    private function narrowing(
        string $alias,
        SearchRequestInterface $request,
        array $criteria,
        TimeScope $scope,
        bool $afterCursor
    ): array {
        $connection = $this->connection();
        $time = $alias . '.occurred_at_ms';
        $conditions = [match ($scope) {
            TimeScope::InRange => $connection->quoteInto($time . ' >= ?', $request->getFromMs())
                . ' AND ' . $connection->quoteInto($time . ' <= ?', $request->getToMs()),
            TimeScope::Outside => '(' . $connection->quoteInto($time . ' < ?', $request->getFromMs())
                . ' OR ' . $connection->quoteInto($time . ' > ?', $request->getToMs()) . ')',
        }];

        $changeChannels = $this->changeChannels->codes();
        if ($request->isChangesOnly() && $changeChannels !== []) {
            $conditions[] = $connection->quoteInto($alias . '.channel IN (?)', $changeChannels);
        }

        $cursor = $request->getCursor();
        if ($afterCursor && $cursor !== null) {
            // Expanded rather than a row comparison, which MariaDB does not turn into an index range
            $conditions[] = $connection->quoteInto($time . ' <= ?', $cursor->getOccurredAtMs())
                . ' AND (' . $connection->quoteInto($time . ' < ?', $cursor->getOccurredAtMs())
                . ' OR ' . $connection->quoteInto($alias . '.entity_id < ?', $cursor->getActivityId()) . ')';
        }

        foreach ($criteria as $criterion) {
            if (!$criterion->isResidual()) {
                $conditions[] = $criterion->render($alias);
            }
        }

        return $conditions;
    }

    /**
     * The residual filters
     *
     * @param list<ResolvedCriterion> $criteria
     * @return list<ResolvedCriterion>
     */
    private function residual(array $criteria): array
    {
        return array_values(array_filter($criteria, static fn (ResolvedCriterion $c): bool => $c->isResidual()));
    }

    /**
     * Add finished conditions to a query
     *
     * @param Select $select
     * @param list<string> $conditions
     * @return Select
     */
    private function where(Select $select, array $conditions): Select
    {
        foreach ($conditions as $condition) {
            $select->where($condition, null, Select::TYPE_CONDITION);
        }

        return $select;
    }

    /**
     * The journal table's name
     *
     * @return string
     */
    private function table(): string
    {
        return $this->resourceConnection->getTableName(ActivityResource::TABLE);
    }

    /**
     * The journal connection
     *
     * @return AdapterInterface
     */
    private function connection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
