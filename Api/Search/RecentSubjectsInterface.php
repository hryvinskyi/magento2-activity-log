<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Search;

/**
 * The subjects changed recently, as the journal recorded them, for a module that suggests its records by name
 *
 * Recently means since Hryvinskyi\ActivityLog\Model\Search\Suggestion\RecentWindow::sinceMs(), and changed means an
 * activity of a channel registered as recording changes (Hryvinskyi\ActivityLog\Model\Search\ChangeChannels). With
 * no such channel nothing is changed.
 *
 * @api
 */
interface RecentSubjectsInterface
{
    /**
     * Distinct subjects of these types, the one whose change was recorded last first
     *
     * Each comes with the name recorded with its last recorded change and its number of recent changes; subjects
     * recorded without an id are left out.
     *
     * @param list<string> $subjectTypes
     * @param int $limit Clamped to 1..SuggestionProviderInterface::MAX_SUGGESTIONS
     * @return list<array{type: string, id: string, label: string, count: int}>
     */
    public function recentlyChanged(
        array $subjectTypes,
        int $limit = SuggestionProviderInterface::MAX_SUGGESTIONS
    ): array;
}
