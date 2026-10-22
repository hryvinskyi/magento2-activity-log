<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Actor;

use Hryvinskyi\ActivityLog\Api\Data\ActorInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Model\Actor\AccountNameLookupInterface;
use Hryvinskyi\ActivityLog\Model\Actor\UserActors;
use Hryvinskyi\ActivityLog\Model\Data\Actor;
use Magento\Authorization\Model\UserContextInterface;
use PHPUnit\Framework\TestCase;

class UserActorsTest extends TestCase
{
    public function testAnAccountIsNamedByTheLookupOfItsKind(): void
    {
        $actor = $this->actors()->actorOf(UserContextInterface::USER_TYPE_ADMIN, 7);

        self::assertSame(
            [ActorType::Admin, 'jane', '7'],
            [$actor?->getType(), $actor?->getName(), $actor?->getId()]
        );
    }

    public function testAnAccountWhoseNameCannotBeReadIsNamedByItsId(): void
    {
        self::assertSame('#42', $this->actors()->actorOf(UserContextInterface::USER_TYPE_ADMIN, 42)?->getName());
        self::assertSame(
            [ActorType::Integration, '#3'],
            [
                $this->actors()->actorOf(UserContextInterface::USER_TYPE_INTEGRATION, 3)?->getType(),
                $this->actors()->actorOf(UserContextInterface::USER_TYPE_INTEGRATION, 3)?->getName(),
            ],
            'A kind without a lookup is named by id too'
        );
        self::assertSame(
            ActorType::Customer,
            $this->actors()->actorOf(UserContextInterface::USER_TYPE_CUSTOMER, 5)?->getType()
        );
    }

    public function testAGuestOrAnUnknownTypeOrNoIdIsNoAccount(): void
    {
        $actors = $this->actors();

        self::assertNull($actors->actorOf(UserContextInterface::USER_TYPE_GUEST, 1));
        self::assertNull($actors->actorOf(99, 1));
        self::assertNull($actors->actorOf(UserContextInterface::USER_TYPE_ADMIN, 0));
    }

    /**
     * Actors of accounts, admins named by a lookup that knows admin 7 as "jane"
     *
     * @return UserActors
     */
    private function actors(): UserActors
    {
        $factory = $this->createStub(ActorInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(
            static function (array $data): ActorInterface {
                self::assertInstanceOf(ActorType::class, $data['type']);
                self::assertIsString($data['name']);
                self::assertIsString($data['id']);

                return new Actor($data['type'], $data['name'], $data['id']);
            }
        );
        $admins = $this->createStub(AccountNameLookupInterface::class);
        $admins->method('nameOf')->willReturnCallback(static fn (int $id): ?string => $id === 7 ? 'jane' : null);

        return new UserActors($factory, [UserContextInterface::USER_TYPE_ADMIN => $admins]);
    }
}
