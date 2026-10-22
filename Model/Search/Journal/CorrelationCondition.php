<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Journal;

use Hryvinskyi\ActivityLog\Model\Data\Activity;
use Magento\Framework\App\ResourceConnection;

/**
 * "request": every activity of one operation, by its exact correlation id, on the indexed column
 */
class CorrelationCondition implements JournalConditionInterface
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
    public function getKey(): string
    {
        return 'request';
    }

    /**
     * @inheritDoc
     */
    public function isResidual(string $value): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function condition(string $value, string $alias): ?string
    {
        if (preg_match(Activity::CORRELATION_PATTERN, $value) !== 1) {
            return null;
        }

        return $this->resourceConnection->getConnection()->quoteInto($alias . '.correlation_id = ?', $value);
    }
}
