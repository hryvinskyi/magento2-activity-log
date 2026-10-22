<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterface;
use Hryvinskyi\ActivityLog\Api\Search\SearchKeyDescriptionInterface;

/**
 * What the search bar needs to suggest as the JSON the search page reads once
 *
 * "keys" lists every key with its translated label and hint, the code of the suggestion group its values are offered
 * in, and whether its value names fields, in filters-panel order; "lists" holds each key's suggested values as
 * {value, label, hint, count}, count being null for a list that is not counted.
 */
class SuggestionsPresenter
{
    /**
     * @param KeyDescriptionPool $keyDescriptionPool
     * @param SuggestionPool $suggestionPool
     */
    public function __construct(
        private readonly KeyDescriptionPool $keyDescriptionPool,
        private readonly SuggestionPool $suggestionPool
    ) {
    }

    /**
     * The keys and their suggestions as plain data
     *
     * @return array{
     *     keys: list<array{key: string, label: string, hint: string, group: string, matchesFieldNames: bool}>,
     *     lists: array<string, list<array{value: string, label: string, hint: string, count: int|null}>>
     * }
     */
    public function present(): array
    {
        $lists = [];
        foreach ($this->suggestionPool->lists() as $key => $suggestions) {
            $lists[$key] = array_map(
                static fn (SuggestionInterface $suggestion): array => [
                    'value' => $suggestion->getValue(),
                    'label' => $suggestion->getLabel(),
                    'hint' => $suggestion->getHint(),
                    'count' => $suggestion->getCount(),
                ],
                $suggestions
            );
        }

        return ['keys' => $this->keys(), 'lists' => $lists];
    }

    /**
     * Every key as the search page reads it, in filters-panel order
     *
     * Each with its translated label and hint, its suggestion group and whether its value names fields.
     *
     * @return list<array{key: string, label: string, hint: string, group: string, matchesFieldNames: bool}>
     */
    public function keys(): array
    {
        return array_map(
            static fn (SearchKeyDescriptionInterface $description): array => [
                'key' => $description->getKey(),
                'label' => $description->getLabel(),
                'hint' => $description->getHint(),
                'group' => $description->getGroup()->value,
                'matchesFieldNames' => $description->matchesFieldNames(),
            ],
            $this->keyDescriptionPool->all()
        );
    }
}
