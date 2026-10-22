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
 * Work done on someone's behalf: the activities it records are attributed to that actor
 *
 * A process that carries out what someone asked for earlier (a queue consumer running an admin's mass action) runs
 * the work in a scope of the person who asked, rather than leaving it to the process ("cli", "cron"). Scopes nest;
 * the innermost one decides, and each ends with its work, however the work ends.
 *
 * @api
 * @since 1.2.0
 */
interface ActorScopeInterface
{
    /**
     * Run the work with its activities attributed to the actor, and return what the work returns
     *
     * @template T
     * @param ActorInterface $actor
     * @param callable $work
     * @phpstan-param callable(): T $work
     * @return T
     */
    public function actAs(ActorInterface $actor, callable $work): mixed;
}
