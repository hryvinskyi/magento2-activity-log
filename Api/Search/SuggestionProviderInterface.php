<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterface;

/**
 * The values of one search key that occur in the log, offered while the admin types
 *
 * Registered by the module that serves the key, under a unique name, in the "suggestionProviders" argument of
 * Hryvinskyi\ActivityLog\Model\Search\SuggestionPool. Several providers may serve one key; their lists are joined.
 *
 * The search page asks for the lists once and filters them as the admin types, so a list is read rarely and kept
 * for a few minutes per interface locale; a provider never caches. A list comes from one bounded query on indexed
 * columns, never from a scan of the log: values recorded since Hryvinskyi\ActivityLog\Model\Search\Suggestion\
 * RecentWindow::sinceMs(), or a fixed set, at most MAX_SUGGESTIONS of them, the most frequent first. A list holds no
 * recorded before or after value.
 *
 * @api
 */
interface SuggestionProviderInterface
{
    /**
     * Most suggestions one key's list holds
     */
    public const MAX_SUGGESTIONS = 200;

    /**
     * The key the values are for, such as "by"
     *
     * @return string
     */
    public function getKey(): string;

    /**
     * The values, the most useful first, at most MAX_SUGGESTIONS
     *
     * @return list<SuggestionInterface>
     */
    public function suggestions(): array;
}
