<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Preference;

/**
 * How the current admin user last chose to see the search page: the result view and "Changes only"
 */
interface SearchPreferenceStorageInterface
{
    /**
     * Saved choice; null fields mean "not chosen yet"
     *
     * @return array{view: ResultView|null, changesOnly: bool|null}
     */
    public function load(): array;

    /**
     * Remember a choice
     *
     * @param ResultView $view
     * @param bool $changesOnly
     * @return void
     */
    public function save(ResultView $view, bool $changesOnly): void;
}
