<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Journal;

use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ActivityLog\Model\Source\OutcomeOptions;
use Magento\Framework\App\ResourceConnection;

/**
 * "outcome": an outcome by code or label, on the indexed outcome column
 */
class OutcomeCondition implements JournalConditionInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param OutcomeOptions $outcomeOptions
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly OutcomeOptions $outcomeOptions
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return 'outcome';
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
        $wanted = mb_strtolower($value);
        foreach (Outcome::cases() as $outcome) {
            if ($outcome->value === $wanted
                || mb_strtolower((string) $this->outcomeOptions->label($outcome)) === $wanted
            ) {
                return $this->resourceConnection->getConnection()
                    ->quoteInto($alias . '.outcome = ?', $outcome->value);
            }
        }

        return null;
    }
}
