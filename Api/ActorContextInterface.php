<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

use Hryvinskyi\ActivityLog\Api\Data\ActorInterface;

/**
 * The actor an activity happening now is attributed to
 *
 * Asks each registered actor resolver in order and takes the first answer; when none answers the actor is anonymous.
 *
 * @api
 */
interface ActorContextInterface
{
    /**
     * Current actor
     *
     * @return ActorInterface
     */
    public function current(): ActorInterface;
}
