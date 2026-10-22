<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Suggestion\Journal;

use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCriterionInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Search\SuggestionProviderInterface;
use Hryvinskyi\ActivityLog\Model\Presentation\ActorTypeLabels;
use Hryvinskyi\ActivityLog\Model\ResourceModel\RecentJournalValues;

/**
 * "by": the names of the actors of recent activities, with the kind of actor and how often each acted
 *
 * Each name is offered in double quotes, which the key matches exactly, so picking the user named "admin" finds that
 * user and not every admin; the list shows the name without them. A name too long to quote is offered as it is.
 */
class ActorSuggestions implements SuggestionProviderInterface
{
    /**
     * @param RecentJournalValues $recentValues
     * @param SuggestionInterfaceFactory $suggestionFactory
     * @param ActorTypeLabels $actorTypeLabels
     */
    public function __construct(
        private readonly RecentJournalValues $recentValues,
        private readonly SuggestionInterfaceFactory $suggestionFactory,
        private readonly ActorTypeLabels $actorTypeLabels
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return 'by';
    }

    /**
     * @inheritDoc
     */
    public function suggestions(): array
    {
        $suggestions = [];
        foreach ($this->recentValues->counted(['actor_name', 'actor_type']) as $row) {
            $type = ActorType::tryFrom($row['values']['actor_type']);
            $name = trim($row['values']['actor_name']);
            $quoted = '"' . $name . '"';
            $suggestions[] = $this->suggestionFactory->create()
                ->withValue(
                    mb_strlen($quoted) <= SearchCriterionInterface::MAX_VALUE_LENGTH ? $quoted : $name,
                    $name
                )
                ->withHint($type === null ? '' : (string) $this->actorTypeLabels->label($type))
                ->withCount($row['count']);
        }

        return $suggestions;
    }
}
