<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Api\Search\SearchKeyDescriptionInterface;

/**
 * How each search key is presented
 *
 * The module that serves a key adds its description to the "keyDescriptions" argument of this type. A key has one
 * description: a later item for the same key replaces the earlier one.
 *
 * @api
 */
class KeyDescriptionPool
{
    /**
     * @var array<string, SearchKeyDescriptionInterface>
     */
    private array $byKey = [];

    /**
     * @param array<string,SearchKeyDescriptionInterface> $keyDescriptions
     */
    public function __construct(array $keyDescriptions = [])
    {
        foreach ($keyDescriptions as $description) {
            $this->add($description);
        }
    }

    /**
     * Every description, by sort order, then key
     *
     * @return list<SearchKeyDescriptionInterface>
     */
    public function all(): array
    {
        $descriptions = array_values($this->byKey);
        usort(
            $descriptions,
            static fn (SearchKeyDescriptionInterface $a, SearchKeyDescriptionInterface $b): int =>
                [$a->getSortOrder(), $a->getKey()] <=> [$b->getSortOrder(), $b->getKey()]
        );

        return $descriptions;
    }

    /**
     * Register a description; anything else in the argument fails here, at construction
     *
     * @param SearchKeyDescriptionInterface $description
     * @return void
     */
    private function add(SearchKeyDescriptionInterface $description): void
    {
        $this->byKey[$description->getKey()] = $description;
    }
}
