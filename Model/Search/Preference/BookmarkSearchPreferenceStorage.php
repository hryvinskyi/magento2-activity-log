<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Preference;

use Hryvinskyi\ActivityLog\Model\Preference\BookmarkConfig;

/**
 * Keeps the search page's choice in the admin user's UI bookmarks, apart from the grid's own saved views
 *
 * A stored value the page no longer knows reads as "not chosen yet".
 */
class BookmarkSearchPreferenceStorage implements SearchPreferenceStorageInterface
{
    public const NAMESPACE = 'hryvinskyi_activity_log_search';

    /**
     * @param BookmarkConfig $bookmarkConfig
     */
    public function __construct(
        private readonly BookmarkConfig $bookmarkConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function load(): array
    {
        $config = $this->bookmarkConfig->load(self::NAMESPACE);
        $view = $config['view'] ?? null;
        $changesOnly = $config['changesOnly'] ?? null;

        return [
            'view' => is_string($view) ? ResultView::tryFrom($view) : null,
            'changesOnly' => is_bool($changesOnly) ? $changesOnly : null,
        ];
    }

    /**
     * @inheritDoc
     */
    public function save(ResultView $view, bool $changesOnly): void
    {
        $this->bookmarkConfig->save(
            self::NAMESPACE,
            'Activity search',
            ['view' => $view->value, 'changesOnly' => $changesOnly]
        );
    }
}
