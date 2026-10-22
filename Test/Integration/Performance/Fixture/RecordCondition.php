<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Performance\Fixture;

use Hryvinskyi\ActivityLog\Api\Search\SearchConditionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * "<key>: #<id>" — the changes of one record, by its type and exact id, through the record index
 */
class RecordCondition implements SearchConditionInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param string $key
     * @param string $recordType
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly string $key = 'record',
        private readonly string $recordType = 'item'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @inheritDoc
     */
    public function isResidual(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function activityIds(string $value): ?Select
    {
        if (preg_match('/^#(\d{1,20})$/D', $value, $id) !== 1) {
            return null;
        }

        return $this->resourceConnection->getConnection()->select()
            ->from($this->resourceConnection->getTableName(PerformanceJournal::RECORD_TABLE), ['activity_id'])
            ->where('record_type = ?', $this->recordType)
            ->where('record_id = ?', $id[1]);
    }
}
