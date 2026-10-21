<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model;

use Hryvinskyi\ActivityLog\Api\ActivityRepositoryInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActivityInterface;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Hryvinskyi\ActivityLog\Model\ResourceModel\ActivityRowMapper;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Reads the journal through the row mapper; deleting an activity cascades to its channel detail rows
 */
class ActivityRepository implements ActivityRepositoryInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param ActivityRowMapper $rowMapper
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ActivityRowMapper $rowMapper
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(int $id): ActivityInterface
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->table())
            ->where(ActivityResource::ID_FIELD . ' = ?', $id)
            ->limit(1);

        $row = $connection->fetchRow($select);
        if (!is_array($row)) {
            throw new NoSuchEntityException(__('Activity #%1 no longer exists.', $id));
        }

        return $this->rowMapper->fromRow($row);
    }

    /**
     * @inheritDoc
     */
    public function getByCorrelationId(string $correlationId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->table())
            ->where('correlation_id = ?', $correlationId)
            ->order(['occurred_at_ms ASC', ActivityResource::ID_FIELD . ' ASC']);

        $activities = [];
        foreach ($connection->fetchAll($select) as $row) {
            if (is_array($row)) {
                $activities[] = $this->rowMapper->fromRow($row);
            }
        }

        return $activities;
    }

    /**
     * @inheritDoc
     */
    public function deleteByIds(array $ids): int
    {
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return 0;
        }

        return $this->resourceConnection->getConnection()->delete(
            $this->table(),
            [ActivityResource::ID_FIELD . ' IN (?)' => $ids]
        );
    }

    /**
     * Journal table name with prefix
     *
     * @return string
     */
    private function table(): string
    {
        return $this->resourceConnection->getTableName(ActivityResource::TABLE);
    }
}
