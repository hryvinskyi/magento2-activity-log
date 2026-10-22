<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Suggestion\Journal;

use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Search\SuggestionProviderInterface;
use Hryvinskyi\ActivityLog\Model\ResourceModel\RecentJournalValues;
use Magento\Store\Model\StoreManagerInterface;

/**
 * "store": the store views recent activities happened in, by name, and how often
 *
 * The name is the value: the store key matches a store view by its name, and so do the scopes other modules add to
 * the key, so a store view suggested twice is offered once. The admin area's own store is not a store view and is
 * left out, and so is a store view deleted since.
 */
class StoreViewSuggestions implements SuggestionProviderInterface
{
    /**
     * @param RecentJournalValues $recentValues
     * @param SuggestionInterfaceFactory $suggestionFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly RecentJournalValues $recentValues,
        private readonly SuggestionInterfaceFactory $suggestionFactory,
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
    public function suggestions(): array
    {
        $names = [];
        foreach ($this->storeManager->getStores() as $store) {
            $names[(string) $store->getId()] = trim((string) $store->getName());
        }

        $suggestions = [];
        foreach ($this->recentValues->counted(['store_id']) as $row) {
            $name = $names[$row['values']['store_id']] ?? '';
            if ($name !== '') {
                $suggestions[] = $this->suggestionFactory->create()
                    ->withValue($name)
                    ->withHint((string) __('Store view'))
                    ->withCount($row['count']);
            }
        }

        return $suggestions;
    }
}
