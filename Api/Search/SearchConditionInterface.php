<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Search;

use Magento\Framework\DB\Select;

/**
 * What a search filter means in a module's own tables: the journal ids it matches, as a sub-select
 *
 * Register an implementation in the "conditions" argument of Hryvinskyi\ActivityLog\Model\Search\ConditionPool. A
 * key may have conditions from several modules; an activity matches the filter when it matches any of them.
 *
 * The sub-select is the whole contribution: it is not correlated with the outer query, selects exactly one column of
 * journal ids (the activity id of the module's own table), and never orders or limits — the search places it as
 * "id IN (…)" wherever it needs it and owns the candidate limit. Bind every value through where('… ?', $value),
 * which quotes it into the SQL (named bind parameters are not carried over), escape LIKE wildcards in what a person
 * typed with Magento\Framework\DB\Helper::escapeLikeValue(), and compare varchar id columns with string values so
 * their index is used. The Select is Magento's documented query type: implementations of this interface are database
 * adapters.
 *
 * A condition of the plain-text key ("text") is asked about one word at a time: the search splits what was typed into
 * words, at most eight, and requires each word to be found by the journal or by any module. A word of one or two
 * characters is to be matched as a substring only, never looked up by label or code: such a lookup would name
 * nearly everything.
 *
 * @api
 */
interface SearchConditionInterface
{
    /**
     * The filter key this condition serves, such as "store" (lowercase letters and underscores)
     *
     * @return string
     */
    public function getKey(): string;

    /**
     * Whether the condition matches text outside any index (LIKE '%…%')
     *
     * A residual condition is applied only among the newest candidates the other filters leave; a narrowing condition
     * (an indexed column) is applied to the whole range.
     *
     * @return bool
     */
    public function isResidual(): bool;

    /**
     * Journal ids matching this value, or null when the value can match no activity
     *
     * Null is an answer, not an absence: the filter then matches nothing through this condition.
     *
     * @param string $value Trimmed, 1 to 255 characters, as the person typed it
     * @return Select|null
     */
    public function activityIds(string $value): ?Select;
}
