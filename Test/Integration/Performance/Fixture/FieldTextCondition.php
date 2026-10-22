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
use Magento\Framework\DB\Helper as DbHelper;
use Magento\Framework\DB\Select;

/**
 * Text inside unmasked field values (and, for the "text" key, field names): unindexed, so residual
 */
class FieldTextCondition implements SearchConditionInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param DbHelper $dbHelper
     * @param string $key
     * @param bool $withFieldNames
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DbHelper $dbHelper,
        private readonly string $key = 'value',
        private readonly bool $withFieldNames = false
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
        return true;
    }

    /**
     * @inheritDoc
     */
    public function activityIds(string $value): ?Select
    {
        $connection = $this->resourceConnection->getConnection();
        $like = $this->dbHelper->escapeLikeValue($value, ['position' => 'any']);
        $columns = $this->withFieldNames ? ['field', 'before_value', 'after_value'] : ['before_value', 'after_value'];
        $matches = array_map(
            static fn (string $column): string => $connection->quoteInto($column . ' LIKE ?', $like),
            $columns
        );

        return $connection->select()
            ->from($this->resourceConnection->getTableName(PerformanceJournal::FIELD_TABLE), ['activity_id'])
            ->where('is_masked = ?', 0)
            ->where(implode(' OR ', $matches), null, Select::TYPE_CONDITION);
    }
}
