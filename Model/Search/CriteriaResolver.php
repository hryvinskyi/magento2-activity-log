<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCriterionInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterface;
use Hryvinskyi\ActivityLog\Api\Search\SearchConditionInterface;
use Hryvinskyi\ActivityLog\Model\Search\Journal\JournalConditionInterface;
use Hryvinskyi\ActivityLog\Model\Search\Query\ResolvedCriterion;
use Hryvinskyi\ActivityLog\Model\Search\Query\ResolvedCriterionFactory;
use Hryvinskyi\ActivityLog\Model\Search\Query\TextWords;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * Turns each filter of a request into its SQL alternatives, once per search
 *
 * A key no condition serves is searched as text, "key: value" as typed. A plain-text filter becomes one required
 * group per word (see TextWords): every condition of the text key — the journal's and each module's — is asked about
 * that one word, and an activity matches the group when any of them finds it. An activity matches the filter when it
 * matches every group, so two words may be found in different places: one in the journal, one in what a module
 * recorded. A filter with no word left matches nothing.
 *
 * Contributed conditions are asked for their sub-select here, once, so a lookup they make (a code to ids) is not
 * repeated for every query of the search. A contributed sub-select applied among the capped candidates is tied to the
 * candidate row on its one column, so the database reads only that row's entries through the module's index: placed
 * inside an OR (the alternatives of a filter), it could not be joined and would otherwise be read whole, once per
 * word.
 */
class CriteriaResolver
{
    /**
     * @param ConditionPool $conditionPool
     * @param ResolvedCriterionFactory $resolvedCriterionFactory
     * @param ResourceConnection $resourceConnection
     * @param TextWords $textWords
     */
    public function __construct(
        private readonly ConditionPool $conditionPool,
        private readonly ResolvedCriterionFactory $resolvedCriterionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly TextWords $textWords
    ) {
    }

    /**
     * Every filter of the request, resolved, in the request's order; a plain-text filter as one group per word
     *
     * Each item is required: a matching activity matches all of them.
     *
     * @param SearchRequestInterface $request
     * @return list<ResolvedCriterion>
     */
    public function resolve(SearchRequestInterface $request): array
    {
        $resolved = [];
        foreach ($request->getCriteria() as $criterion) {
            array_push($resolved, ...$this->resolveOne($criterion));
        }

        return $resolved;
    }

    /**
     * One filter, resolved into the groups an activity must all match
     *
     * @param SearchCriterionInterface $criterion
     * @return non-empty-list<ResolvedCriterion>
     */
    private function resolveOne(SearchCriterionInterface $criterion): array
    {
        $key = $criterion->getKey();
        $value = $criterion->getValue();
        if (!$this->conditionPool->serves($key)) {
            $value = $key . ': ' . $value;
            $key = SearchCriterionInterface::TEXT_KEY;
        }

        if ($key !== SearchCriterionInterface::TEXT_KEY) {
            return [$this->group($key, $value)];
        }

        $groups = array_map(
            fn (string $word): ResolvedCriterion => $this->group(SearchCriterionInterface::TEXT_KEY, $word),
            $this->textWords->of($value)
        );

        return $groups === []
            ? [$this->resolvedCriterionFactory->create(['residual' => false, 'alternatives' => []])]
            : $groups;
    }

    /**
     * Every condition of a key asked about one value, as the alternatives of one required group
     *
     * @param string $key
     * @param string $value
     * @return ResolvedCriterion
     */
    private function group(string $key, string $value): ResolvedCriterion
    {
        $residual = false;
        $alternatives = [];
        foreach ($this->conditionPool->journal($key) as $condition) {
            $residual = $residual || $condition->isResidual($value);
            $alternatives[] = $this->journalAlternative($condition, $value);
        }
        foreach ($this->conditionPool->contributed($key) as $condition) {
            $residual = $residual || $condition->isResidual();
            $alternatives[] = $this->contributedAlternative($condition, $value);
        }

        return $this->resolvedCriterionFactory->create(['residual' => $residual, 'alternatives' => $alternatives]);
    }

    /**
     * A journal condition as an alternative
     *
     * @param JournalConditionInterface $condition
     * @param string $value
     * @return \Closure(string,bool):?string
     */
    private function journalAlternative(JournalConditionInterface $condition, string $value): \Closure
    {
        return static fn (string $alias, bool $amongCandidates): ?string => $condition->condition($value, $alias);
    }

    /**
     * A contributed condition as an alternative: its sub-select, placed as "id IN (…)" on any alias
     *
     * Among the candidates it is tied to the row it is asked about. The sub-select is embedded as SQL text, which
     * already holds its quoted values.
     *
     * @param SearchConditionInterface $condition
     * @param string $value
     * @return \Closure(string,bool):?string
     */
    private function contributedAlternative(SearchConditionInterface $condition, string $value): \Closure
    {
        $activityIds = $condition->activityIds($value);
        if ($activityIds === null) {
            return static fn (string $alias, bool $amongCandidates): ?string => null;
        }

        $column = $this->selectedColumn($activityIds);
        $sql = $activityIds->assemble();

        return static function (string $alias, bool $amongCandidates) use ($activityIds, $column, $sql): string {
            if (!$amongCandidates || $column === null) {
                return $alias . '.entity_id IN (' . $sql . ')';
            }

            $restricted = clone $activityIds;
            $restricted->where($column . ' = ' . $alias . '.entity_id', null, Select::TYPE_CONDITION);

            return $alias . '.entity_id IN (' . $restricted->assemble() . ')';
        };
    }

    /**
     * The one column a sub-select selects, as SQL usable in its WHERE, or null when it cannot be told
     *
     * @param Select $select
     * @return string|null
     */
    private function selectedColumn(Select $select): ?string
    {
        $columns = $select->getPart(Select::COLUMNS);
        if (!is_array($columns) || count($columns) !== 1 || !is_array($columns[0] ?? null)) {
            return null;
        }

        [$correlation, $column] = $columns[0] + [null, null];
        if ($column instanceof \Zend_Db_Expr) {
            return (string) $column;
        }

        return is_string($correlation) && is_string($column) && $column !== '*'
            ? $this->resourceConnection->getConnection()->quoteIdentifier([$correlation, $column])
            : null;
    }
}
