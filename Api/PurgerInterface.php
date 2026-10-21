<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

/**
 * Removes activities older than their retention allows
 *
 * The retention job runs every purger registered in its "purgers" argument. A channel whose activities need a
 * retention finer than one number of days per channel registers its own purger and returns null from
 * ChannelInterface::getRetentionDays().
 *
 * @api
 */
interface PurgerInterface
{
    /**
     * Remove what is past retention
     *
     * @return int Number of activities removed
     */
    public function purge(): int;
}
