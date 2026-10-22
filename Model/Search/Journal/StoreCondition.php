<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Journal;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * "store": the store views an activity ran in, by code or name, on the store column
 *
 * A code or a whole name matches, case aside; a part of a name matches only when no store view has that code or
 * name, so "Default Store View" never also finds "Default Store View 2". Several names separated by "|" match any of
 * them.
 */
class StoreCondition implements JournalConditionInterface
{
    /**
     * What separates several store views in one value
     */
    public const ALTERNATIVES = '|';

    /**
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return 'store';
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
        $storeIds = [];
        foreach (explode(self::ALTERNATIVES, $value) as $part) {
            $part = trim($part);
            if ($part !== '') {
                array_push($storeIds, ...$this->storeIds($part));
            }
        }

        return $storeIds === []
            ? null
            : $this->resourceConnection->getConnection()->quoteInto(
                $alias . '.store_id IN (?)',
                array_values(array_unique($storeIds))
            );
    }

    /**
     * Ids of the store views one name stands for: those it names exactly, else those whose name contains it
     *
     * @param string $value
     * @return list<int>
     */
    private function storeIds(string $value): array
    {
        $wanted = mb_strtolower($value);
        $exact = [];
        $partial = [];
        foreach ($this->storeManager->getStores(true) as $store) {
            $name = (string) $store->getName();
            if (mb_strtolower((string) $store->getCode()) === $wanted || mb_strtolower($name) === $wanted) {
                $exact[] = (int) $store->getId();
                continue;
            }
            if (mb_stripos($name, $value) !== false) {
                $partial[] = (int) $store->getId();
            }
        }

        return $exact !== [] ? $exact : $partial;
    }
}
