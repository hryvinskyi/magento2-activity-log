<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

use Hryvinskyi\ActivityLog\Api\Data\ActivityInterface;

/**
 * Writes activities to the journal
 *
 * Recording never breaks the operation being recorded: when the journal is disabled nothing is written, and a
 * failure to write is logged. Either way the result is null.
 *
 * @api
 */
interface ActivityRecorderInterface
{
    /**
     * Record the activity and return its journal id, or null when nothing was written
     *
     * @param ActivityInterface $activity
     * @return int|null
     */
    public function record(ActivityInterface $activity): ?int;
}
