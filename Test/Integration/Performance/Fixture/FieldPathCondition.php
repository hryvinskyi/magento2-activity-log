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
 * "<key>: a/b/c" — changes of a field path, exactly or as a whole-segment prefix, through the field index
 */
class FieldPathCondition implements SearchConditionInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param DbHelper $dbHelper
     * @param string $key
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DbHelper $dbHelper,
        private readonly string $key = 'path'
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
        $connection = $this->resourceConnection->getConnection();

        return $connection->select()
            ->from($this->resourceConnection->getTableName(PerformanceJournal::FIELD_TABLE), ['activity_id'])
            ->where(
                $connection->quoteInto('field = ?', $value) . ' OR '
                . $connection->quoteInto('field LIKE ?', $this->dbHelper->escapeLikeValue($value) . '/%'),
                null,
                Select::TYPE_CONDITION
            );
    }
}
