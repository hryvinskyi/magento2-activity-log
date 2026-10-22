<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\ResourceModel;

use Hryvinskyi\ActivityLog\Api\Data\Search\ActivityFactInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\ActivityFactInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Search\ActivityFactsInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Time and actor of given activities from the journal: one query on its primary key, however many are named
 */
class ActivityFacts implements ActivityFactsInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param ActivityFactInterfaceFactory $factFactory
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ActivityFactInterfaceFactory $factFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function of(array $activityIds): array
    {
        if (count($activityIds) > self::MAX_ACTIVITIES) {
            throw new \InvalidArgumentException(sprintf(
                'At most %d activities may be named at once, not %d.',
                self::MAX_ACTIVITIES,
                count($activityIds)
            ));
        }

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $activityIds),
            static fn (int $id): bool => $id > 0
        )));
        if ($ids === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $facts = [];
        foreach ($connection->fetchAll(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName(Activity::TABLE),
                    ['entity_id', 'occurred_at_ms', 'actor_name']
                )
                ->where('entity_id IN (?)', $ids)
        ) as $row) {
            $fact = is_array($row) ? $this->fact($row) : null;
            if ($fact !== null) {
                $facts[$fact->getActivityId()] = $fact;
            }
        }

        return $facts;
    }

    /**
     * The facts of one row, or null for a row that does not hold them
     *
     * @param array<mixed> $row
     * @return ActivityFactInterface|null
     */
    private function fact(array $row): ?ActivityFactInterface
    {
        $id = $row['entity_id'] ?? null;
        $ms = $row['occurred_at_ms'] ?? null;
        $actor = $row['actor_name'] ?? null;
        if (!is_numeric($id) || !is_numeric($ms) || (int) $id < 1 || (int) $ms < 0) {
            return null;
        }

        return $this->factFactory->create()
            ->withActivity((int) $id, (int) $ms, is_scalar($actor) ? (string) $actor : '');
    }
}
