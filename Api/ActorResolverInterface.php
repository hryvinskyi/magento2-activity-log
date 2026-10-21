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
 * One way of knowing who is acting right now
 *
 * Register an implementation in the "resolvers" argument of the actor context, with a sortOrder: a resolver that
 * knows more (a delegating client acting for an admin) goes before one that knows less (the admin session).
 *
 * @api
 */
interface ActorResolverInterface
{
    /**
     * The current actor, or null when this resolver cannot tell
     *
     * @return ActorInterface|null
     */
    public function resolve(): ?ActorInterface;
}
