<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Actor;

use Hryvinskyi\ActivityLog\Api\Data\ActorInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Api\UserActorsInterface;
use Magento\Authorization\Model\UserContextInterface;

/**
 * Admins, integrations and customers as actors, each named through the lookup of its kind of account
 */
class UserActors implements UserActorsInterface
{
    private const ACCOUNT_TYPES = [
        UserContextInterface::USER_TYPE_ADMIN => ActorType::Admin,
        UserContextInterface::USER_TYPE_INTEGRATION => ActorType::Integration,
        UserContextInterface::USER_TYPE_CUSTOMER => ActorType::Customer,
    ];

    /**
     * @param ActorInterfaceFactory $actorFactory
     * @param array<int|string,AccountNameLookupInterface> $nameLookups Keyed by UserContextInterface user type
     */
    public function __construct(
        private readonly ActorInterfaceFactory $actorFactory,
        private readonly array $nameLookups = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function actorOf(int $userType, int $userId): ?ActorInterface
    {
        $type = self::ACCOUNT_TYPES[$userType] ?? null;
        if ($type === null || $userId <= 0) {
            return null;
        }

        $name = ($this->nameLookups[$userType] ?? null)?->nameOf($userId);

        return $this->actorFactory->create([
            'type' => $type,
            'name' => $name ?? sprintf('#%d', $userId),
            'id' => (string) $userId,
        ]);
    }
}
