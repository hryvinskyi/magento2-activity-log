<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Journal;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Helper as DbHelper;

/**
 * "via": a name the client an actor worked through contains; no index serves it
 */
class ViaClientCondition implements JournalConditionInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param DbHelper $dbHelper
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DbHelper $dbHelper
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return 'via';
    }

    /**
     * @inheritDoc
     */
    public function isResidual(string $value): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function condition(string $value, string $alias): ?string
    {
        return $this->resourceConnection->getConnection()->quoteInto(
            $alias . '.via_client LIKE ?',
            $this->dbHelper->escapeLikeValue($value, ['position' => 'any'])
        );
    }
}
