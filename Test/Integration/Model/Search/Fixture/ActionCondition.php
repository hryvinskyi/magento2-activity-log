<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture;

use Hryvinskyi\ActivityLog\Api\Search\SearchConditionInterface;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;

/**
 * A contributed condition for tests: the activities whose action is the value behind a prefix
 *
 * It stands in for another module's condition, which would read that module's own table; the value "nothing" is
 * answered with no sub-select, as a condition does when the value can match nothing.
 */
class ActionCondition implements SearchConditionInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param string $key
     * @param string $prefix
     * @param bool $residual
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly string $key = 'tagged',
        private readonly string $prefix = '',
        private readonly bool $residual = false
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
        return $this->residual;
    }

    /**
     * @inheritDoc
     */
    public function activityIds(string $value): ?Select
    {
        if ($value === 'nothing') {
            return null;
        }

        return $this->resourceConnection->getConnection()->select()
            ->from($this->resourceConnection->getTableName(ActivityResource::TABLE), ['entity_id'])
            ->where('action = ?', $this->prefix . $value);
    }
}
