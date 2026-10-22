<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Dashboard;

use Hryvinskyi\ActivityLog\Model\Preference\BookmarkConfig;

/**
 * Keeps the choice in the admin user's UI bookmarks, where Magento keeps grid views
 */
class BookmarkPreferenceStorage implements PreferenceStorageInterface
{
    public const NAMESPACE = 'hryvinskyi_activity_log_dashboard';

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

        $periodDays = is_int($config['periodDays'] ?? null) ? $config['periodDays'] : null;
        $channels = null;
        if (is_array($config['channels'] ?? null)) {
            $channels = array_values(array_filter($config['channels'], 'is_string'));
        }

        return ['periodDays' => $periodDays, 'channels' => $channels];
    }

    /**
     * @inheritDoc
     */
    public function save(int $periodDays, array $channels): void
    {
        $this->bookmarkConfig->save(
            self::NAMESPACE,
            'Activity dashboard',
            ['periodDays' => $periodDays, 'channels' => $channels]
        );
    }
}
