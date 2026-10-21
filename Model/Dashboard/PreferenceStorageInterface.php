<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Dashboard;

/**
 * The period and channels the current admin user last chose on the dashboard
 */
interface PreferenceStorageInterface
{
    /**
     * Saved choice; null fields mean "not chosen yet"
     *
     * @return array{periodDays: int|null, channels: list<string>|null}
     */
    public function load(): array;

    /**
     * Remember a choice
     *
     * @param int $periodDays
     * @param list<string> $channels
     * @return void
     */
    public function save(int $periodDays, array $channels): void;
}
