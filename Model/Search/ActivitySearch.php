<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCursorInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchPageInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchPageInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterface;
use Hryvinskyi\ActivityLog\Api\Search\ActivitySearchInterface;
use Hryvinskyi\ActivityLog\Model\Search\Query\SearchQueryBuilder;
use Hryvinskyi\ActivityLog\Model\Search\Query\TimeScope;
use Magento\Framework\App\ResourceConnection;

/**
 * The journal search: a keyset page newest first, then, for the first page only, the counts around it
 *
 * The page reads one row more than asked to know whether an older match exists. Counts describe the whole request:
 * the total in the range on every first page; matches outside the range (and the newest of them) when the first page
 * is empty or there are filters; the overall count when there are filters; and whether the journal is empty, asked
 * only when the page is.
 */
class ActivitySearch implements ActivitySearchInterface
{
    /**
     * @param CriteriaResolver $criteriaResolver
     * @param SearchQueryBuilder $queryBuilder
     * @param SearchCounter $counter
     * @param EventViewAssembler $eventViewAssembler
     * @param SearchPageInterfaceFactory $pageFactory
     * @param SearchCursorInterfaceFactory $cursorFactory
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly CriteriaResolver $criteriaResolver,
        private readonly SearchQueryBuilder $queryBuilder,
        private readonly SearchCounter $counter,
        private readonly EventViewAssembler $eventViewAssembler,
        private readonly SearchPageInterfaceFactory $pageFactory,
        private readonly SearchCursorInterfaceFactory $cursorFactory,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @inheritDoc
     */
    public function search(SearchRequestInterface $request): SearchPageInterface
    {
        $criteria = $this->criteriaResolver->resolve($request);

        $select = $this->queryBuilder->matches($request, $criteria, TimeScope::InRange, true)
            ->columns($this->eventViewAssembler->columns(), SearchQueryBuilder::ALIAS)
            ->order([SearchQueryBuilder::ALIAS . '.occurred_at_ms DESC', SearchQueryBuilder::ALIAS . '.entity_id DESC'])
            ->limit($request->getLimit() + 1);
        $rows = $this->rows($this->resourceConnection->getConnection()->fetchAll($select));

        $nextCursor = null;
        if (count($rows) > $request->getLimit()) {
            $rows = array_slice($rows, 0, $request->getLimit());
            $last = $rows[count($rows) - 1];
            $nextCursor = $this->cursorFactory->create()->withPosition($last['occurred_at_ms'], $last['entity_id']);
        }

        $page = [
            'events' => $this->eventViewAssembler->assemble($rows),
            'nextCursor' => $nextCursor,
            'narrowed' => $this->queryBuilder->exceedsCandidateLimit($request, $criteria, TimeScope::InRange, true),
            'journalEmpty' => $rows === [] && $this->counter->isJournalEmpty(),
        ];

        if ($request->getCursor() === null) {
            $total = $this->counter->inRange($request, $criteria);
            $page['total'] = $total;
            if ($rows === [] || $criteria !== []) {
                $outside = $this->counter->outside($request, $criteria);
                $page['outside'] = $outside;
                $page['latestOutsideMs'] = $outside->getCount() > 0
                    ? $this->counter->latestOutsideMs($request, $criteria)
                    : null;
                $page['overall'] = $criteria !== [] ? $this->counter->sum($total, $outside) : null;
            }
        }

        return $this->pageFactory->create($page);
    }

    /**
     * Journal rows with their id and time typed, the other columns as fetched
     *
     * @param array<mixed> $fetched
     * @return list<array<string, mixed>&array{entity_id: int, occurred_at_ms: int}>
     */
    private function rows(array $fetched): array
    {
        $rows = [];
        foreach ($fetched as $row) {
            if (is_array($row) && is_numeric($row['entity_id'] ?? null) && is_numeric($row['occurred_at_ms'] ?? null)) {
                $typed = [];
                foreach ($row as $column => $value) {
                    $typed[(string) $column] = $value;
                }
                $typed['entity_id'] = (int) $row['entity_id'];
                $typed['occurred_at_ms'] = (int) $row['occurred_at_ms'];
                $rows[] = $typed;
            }
        }

        return $rows;
    }
}
