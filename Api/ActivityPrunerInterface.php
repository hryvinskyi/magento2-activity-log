<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

/**
 * Removes a channel's old activities
 *
 * For a channel module whose retention is finer than one number of days per channel: its own purger decides the
 * cut-off per subject type and asks for the removal here. Channel detail rows go with their activities.
 *
 * @api
 */
interface ActivityPrunerInterface
{
    /**
     * Remove the channel's activities that happened before the cut-off
     *
     * @param string $channel Channel code
     * @param int $beforeMs Cut-off, in milliseconds since the Unix epoch
     * @param list<string> $subjectTypes Only activities about these subject types; every activity of the channel
     *                                   when empty
     * @return int Number of activities removed
     */
    public function prune(string $channel, int $beforeMs, array $subjectTypes = []): int;
}
