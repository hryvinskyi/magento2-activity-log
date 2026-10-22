<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Actor\Resolver;

use Hryvinskyi\ActivityLog\Api\ActorResolverInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Api\UserActorsInterface;
use Magento\Authorization\Model\UserContextInterface;

/**
 * The account Magento authenticated for this request: the admin session in the back office, the token's owner on
 * the web API
 */
class UserContextActorResolver implements ActorResolverInterface
{
    /**
     * @param UserContextInterface $userContext
     * @param ActorInterfaceFactory $actorFactory
     * @param UserActorsInterface $userActors
     */
    public function __construct(
        private readonly UserContextInterface $userContext,
        private readonly ActorInterfaceFactory $actorFactory,
        private readonly UserActorsInterface $userActors
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(): ?ActorInterface
    {
        $userType = $this->userContext->getUserType();
        if ($userType === UserContextInterface::USER_TYPE_GUEST) {
            return $this->actorFactory->create(['type' => ActorType::Guest, 'name' => 'guest']);
        }

        $userId = $this->userContext->getUserId();

        return $userType === null || $userId === null ? null : $this->userActors->actorOf($userType, (int) $userId);
    }
}
