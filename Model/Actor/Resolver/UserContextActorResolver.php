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
use Hryvinskyi\ActivityLog\Model\Actor\AccountNameLookupInterface;
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
     * @param array<int|string,AccountNameLookupInterface> $nameLookups Keyed by UserContextInterface user type
     */
    public function __construct(
        private readonly UserContextInterface $userContext,
        private readonly ActorInterfaceFactory $actorFactory,
        private readonly array $nameLookups = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(): ?ActorInterface
    {
        $userType = $this->userContext->getUserType();
        $type = $this->actorType($userType);
        if ($type === null) {
            return null;
        }

        if ($type === ActorType::Guest) {
            return $this->actorFactory->create(['type' => ActorType::Guest, 'name' => 'guest']);
        }

        $userId = $this->userContext->getUserId();
        if ($userId === null || (int) $userId <= 0) {
            return null;
        }

        $id = (int) $userId;
        $lookup = $this->nameLookups[(int) $userType] ?? null;
        $name = $lookup?->nameOf($id);

        return $this->actorFactory->create([
            'type' => $type,
            'name' => $name ?? sprintf('#%d', $id),
            'id' => (string) $id,
        ]);
    }

    /**
     * Actor type for a Magento user type, or null when nobody is authenticated
     *
     * @param int|null $userType
     * @return ActorType|null
     */
    private function actorType(?int $userType): ?ActorType
    {
        return match ($userType) {
            UserContextInterface::USER_TYPE_ADMIN => ActorType::Admin,
            UserContextInterface::USER_TYPE_INTEGRATION => ActorType::Integration,
            UserContextInterface::USER_TYPE_CUSTOMER => ActorType::Customer,
            UserContextInterface::USER_TYPE_GUEST => ActorType::Guest,
            default => null,
        };
    }
}
