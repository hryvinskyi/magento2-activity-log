<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\ResourceModel;

use Hryvinskyi\ActivityLog\Api\Search\SuggestionProviderInterface;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\RecentWindow;
use Magento\Framework\App\ResourceConnection;

/**
 * The distinct values of journal columns among the recent activities, with how often each occurs
 *
 * One grouped query bounded by the time index: the window's rows are read once, never the whole journal.
 */
class RecentJournalValues
{
    /**
     * Columns a list may be drawn from
     */
    private const COLUMNS = ['actor_type', 'actor_name', 'via_client', 'store_id'];

    /**
     * @param ResourceConnection $resourceConnection
     * @param RecentWindow $window
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly RecentWindow $window
    ) {
    }

    /**
     * Combinations of the columns' values, each with its number of recent activities, the most frequent first
     *
     * Rows where any of the columns is empty or blank are left out; at most
     * SuggestionProviderInterface::MAX_SUGGESTIONS rows.
     *
     * @param list<string> $columns
     * @return list<array{values: array<string, string>, count: int}>
     * @throws \InvalidArgumentException When a column is not one a list may be drawn from
     */
    public function counted(array $columns): array
    {
        if ($columns === [] || array_diff($columns, self::COLUMNS) !== []) {
            throw new \InvalidArgumentException(sprintf('Cannot list "%s".', implode(', ', $columns)));
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName(Activity::TABLE),
                array_merge($columns, ['occurrences' => 'COUNT(*)'])
            )
            ->where('occurred_at_ms >= ?', $this->window->sinceMs())
            ->group($columns)
            ->order(array_merge(['occurrences DESC'], $columns))
            ->limit(SuggestionProviderInterface::MAX_SUGGESTIONS);
        foreach ($columns as $column) {
            $select->where($column . ' IS NOT NULL');
        }

        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $values = [];
            foreach ($columns as $column) {
                $values[$column] = is_scalar($row[$column] ?? null) ? trim((string) $row[$column]) : '';
            }
            if (in_array('', $values, true)) {
                continue;
            }
            $count = $row['occurrences'] ?? 0;
            $rows[] = ['values' => $values, 'count' => is_numeric($count) ? (int) $count : 0];
        }

        return $rows;
    }
}
