<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Api\Search\SearchConditionInterface;
use Hryvinskyi\ActivityLog\Model\Search\Journal\JournalConditionInterface;

/**
 * The conditions that serve each search key
 *
 * A module serves a key by adding a SearchConditionInterface to the "conditions" argument of this type. The journal's
 * own keys come through "journalConditions". A key may be served several times, by the journal and by modules alike.
 *
 * @api
 */
class ConditionPool
{
    /**
     * @var array<string, list<SearchConditionInterface>>
     */
    private array $contributed = [];

    /**
     * @var array<string, list<JournalConditionInterface>>
     */
    private array $journal = [];

    /**
     * @param array<string,SearchConditionInterface> $conditions
     * @param array<string,JournalConditionInterface> $journalConditions
     */
    public function __construct(array $conditions = [], array $journalConditions = [])
    {
        foreach ($conditions as $condition) {
            $this->addContributed($condition);
        }
        foreach ($journalConditions as $condition) {
            $this->addJournal($condition);
        }
    }

    /**
     * Whether any condition serves the key
     *
     * @param string $key
     * @return bool
     */
    public function serves(string $key): bool
    {
        return isset($this->contributed[$key]) || isset($this->journal[$key]);
    }

    /**
     * Conditions other modules contribute for the key
     *
     * @param string $key
     * @return list<SearchConditionInterface>
     */
    public function contributed(string $key): array
    {
        return $this->contributed[$key] ?? [];
    }

    /**
     * The journal's own conditions for the key
     *
     * @param string $key
     * @return list<JournalConditionInterface>
     */
    public function journal(string $key): array
    {
        return $this->journal[$key] ?? [];
    }

    /**
     * Register a contributed condition; anything else in the argument fails here, at construction
     *
     * @param SearchConditionInterface $condition
     * @return void
     */
    private function addContributed(SearchConditionInterface $condition): void
    {
        $this->contributed[$condition->getKey()][] = $condition;
    }

    /**
     * Register a journal condition; anything else in the argument fails here, at construction
     *
     * @param JournalConditionInterface $condition
     * @return void
     */
    private function addJournal(JournalConditionInterface $condition): void
    {
        $this->journal[$condition->getKey()][] = $condition;
    }
}
