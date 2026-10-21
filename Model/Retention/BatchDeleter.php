<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Retention;

use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Magento\Framework\App\ResourceConnection;

/**
 * Deletes journal rows older than a cut-off in batches, so a large backlog does not hold a long lock
 *
 * Channel detail rows go with them through their foreign keys.
 */
class BatchDeleter
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param int $batchSize
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly int $batchSize = 5000
    ) {
    }

    /**
     * Delete the rows that happened before the cut-off and match every condition
     *
     * @param int $beforeMs
     * @param array<string,string|list<string>> $conditions Where-clause with one "?" => the value bound to it
     * @return int Rows deleted
     */
    public function deleteOlderThan(int $beforeMs, array $conditions = []): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(ActivityResource::TABLE);

        $removed = 0;
        do {
            $select = $connection->select()
                ->from($table, [ActivityResource::ID_FIELD])
                ->where('occurred_at_ms < ?', $beforeMs)
                ->limit($this->batchSize);
            foreach ($conditions as $condition => $value) {
                $select->where($condition, $value);
            }

            $ids = [];
            foreach ($connection->fetchCol($select) as $id) {
                if (is_numeric($id)) {
                    $ids[] = (int) $id;
                }
            }
            if ($ids === []) {
                break;
            }

            $removed += $connection->delete($table, [ActivityResource::ID_FIELD . ' IN (?)' => $ids]);
        } while (count($ids) === $this->batchSize);

        return $removed;
    }
}
