<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Magento\Framework\App\ResourceConnection;

/**
 * How many activities each operation of a result page did, over the whole journal
 *
 * One grouped query on the correlation index per page. The range and "changes only" do not apply: an operation's
 * other activities may sit just outside the range, or record no change, and the page still offers them.
 */
class SiblingCounter
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Activities per operation, keyed by correlation id; an id the journal no longer holds is left out
     *
     * @param list<string> $correlationIds
     * @return array<string, int>
     */
    public function activitiesPerOperation(array $correlationIds): array
    {
        $correlationIds = array_values(array_unique($correlationIds));
        if ($correlationIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName(ActivityResource::TABLE),
                ['correlation_id', 'activities' => 'COUNT(*)']
            )
            ->where('correlation_id IN (?)', $correlationIds)
            ->group('correlation_id');

        $counts = [];
        foreach ($connection->fetchPairs($select) as $correlationId => $activities) {
            if (is_numeric($activities)) {
                $counts[(string) $correlationId] = (int) $activities;
            }
        }

        return $counts;
    }
}
