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
 * The actor a Magento account is: an admin, an integration or a customer, named as people know it
 *
 * The account is given as Magento's user context gives it, by user type and id, so an account stored with work to do
 * later (a bulk action's owner) names the same actor the session did.
 *
 * @api
 * @since 1.2.0
 */
interface UserActorsInterface
{
    /**
     * The actor of an account, or null when the type is not an account's (or the id names none)
     *
     * An account whose name cannot be read is named by its id: "#42".
     *
     * @param int $userType A Magento\Authorization\Model\UserContextInterface user type
     * @param int $userId
     * @return ActorInterface|null
     */
    public function actorOf(int $userType, int $userId): ?ActorInterface;
}
