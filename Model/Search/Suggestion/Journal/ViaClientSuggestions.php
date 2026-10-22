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

/**
 * "via": the clients actors worked through in recent activities, and how often each was used
 */
class ViaClientSuggestions implements SuggestionProviderInterface
{
    /**
     * @param RecentJournalValues $recentValues
     * @param SuggestionInterfaceFactory $suggestionFactory
     */
    public function __construct(
        private readonly RecentJournalValues $recentValues,
        private readonly SuggestionInterfaceFactory $suggestionFactory
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
    public function suggestions(): array
    {
        $suggestions = [];
        foreach ($this->recentValues->counted(['via_client']) as $row) {
            $suggestions[] = $this->suggestionFactory->create()
                ->withValue($row['values']['via_client'])
                ->withHint((string) __('Client'))
                ->withCount($row['count']);
        }

        return $suggestions;
    }
}
