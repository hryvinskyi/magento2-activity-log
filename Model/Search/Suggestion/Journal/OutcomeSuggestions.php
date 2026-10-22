<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Suggestion\Journal;

use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Search\SuggestionProviderInterface;
use Hryvinskyi\ActivityLog\Model\Source\OutcomeOptions;

/**
 * "outcome": every outcome, by code, with its label
 */
class OutcomeSuggestions implements SuggestionProviderInterface
{
    /**
     * @param OutcomeOptions $outcomeOptions
     * @param SuggestionInterfaceFactory $suggestionFactory
     */
    public function __construct(
        private readonly OutcomeOptions $outcomeOptions,
        private readonly SuggestionInterfaceFactory $suggestionFactory
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
    public function suggestions(): array
    {
        $suggestions = [];
        foreach (Outcome::cases() as $outcome) {
            $suggestions[] = $this->suggestionFactory->create()
                ->withValue($outcome->value, (string) $this->outcomeOptions->label($outcome));
        }

        return $suggestions;
    }
}
