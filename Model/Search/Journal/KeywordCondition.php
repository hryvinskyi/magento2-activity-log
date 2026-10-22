<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Journal;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCriterionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Helper as DbHelper;

/**
 * "text": one word somewhere in the action, subject, actor name or correlation id
 *
 * The search hands it one word of a plain-text filter at a time (see Query\TextWords), as typed: it must appear —
 * case aside, "%" and "_" taken literally — in at least one of the four columns. No index serves it, so it is
 * residual: the search applies it only among the newest candidates the other filters leave, which keeps its cost
 * bounded. A FULLTEXT index would not: InnoDB builds the whole match set of a common word before anything narrows it.
 */
class KeywordCondition implements JournalConditionInterface
{
    private const COLUMNS = ['action', 'subject_label', 'actor_name', 'correlation_id'];

    /**
     * @param ResourceConnection $resourceConnection
     * @param DbHelper $dbHelper
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DbHelper $dbHelper
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return SearchCriterionInterface::TEXT_KEY;
    }

    /**
     * @inheritDoc
     */
    public function isResidual(string $value): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function condition(string $value, string $alias): ?string
    {
        if ($value === '') {
            return null;
        }

        $connection = $this->resourceConnection->getConnection();
        $like = $this->dbHelper->escapeLikeValue($value, ['position' => 'any']);
        $anyColumn = array_map(
            static fn (string $column): string => $connection->quoteInto($alias . '.' . $column . ' LIKE ?', $like),
            self::COLUMNS
        );

        return implode(' OR ', $anyColumn);
    }
}
