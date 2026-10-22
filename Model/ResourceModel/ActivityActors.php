<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\ResourceModel;

use Hryvinskyi\ActivityLog\Api\Search\ActivityActorsInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Actor names of given activities from the journal: one query on its primary key
 */
class ActivityActors implements ActivityActorsInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @inheritDoc
     */
    public function namesOf(array $activityIds): array
    {
        if (count($activityIds) > self::MAX_ACTIVITIES) {
            throw new \InvalidArgumentException(sprintf(
                'At most %d activities may be named at once, not %d.',
                self::MAX_ACTIVITIES,
                count($activityIds)
            ));
        }

        $ids = array_values(array_unique(array_map('intval', $activityIds)));
        if ($ids === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $names = [];
        foreach ($connection->fetchCol(
            $connection->select()
                ->distinct()
                ->from($this->resourceConnection->getTableName(Activity::TABLE), ['actor_name'])
                ->where('entity_id IN (?)', $ids)
                ->where('actor_name <> ?', '')
                ->order('actor_name ASC')
                ->limit(self::MAX_NAMES)
        ) as $name) {
            if (is_scalar($name) && trim((string) $name) !== '') {
                $names[] = (string) $name;
            }
        }

        return $names;
    }
}
