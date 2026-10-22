<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Search;

/**
 * The subjects the journal recorded under a name, for a module that finds records people still remember by a name
 * the record itself no longer has (it was deleted or renamed)
 *
 * @api
 */
interface SubjectLookupInterface
{
    /**
     * Most subjects one lookup returns
     */
    public const MAX_SUBJECTS = 50;

    /**
     * Distinct subjects of these types whose recorded name contains the text
     *
     * Case aside, wildcards in the text taken literally; subjects recorded without an id are left out.
     *
     * @param list<string> $subjectTypes
     * @param string $labelPart
     * @param int $limit Clamped to 1..MAX_SUBJECTS
     * @return list<array{type: string, id: string}>
     */
    public function subjectsLike(array $subjectTypes, string $labelPart, int $limit = self::MAX_SUBJECTS): array;
}
