<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model;

use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Model\Actor\Resolver\UserContextActorResolver;
use Magento\Authorization\Model\UserContextInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\User\Test\Fixture\User as UserFixture;
use PHPUnit\Framework\TestCase;

/**
 * The resolver as the container builds it, with the real name lookups
 */
#[DbIsolation(true)]
class UserContextActorResolverTest extends TestCase
{
    #[DataFixture(UserFixture::class, ['username' => 'jane.doe'], 'user')]
    public function testAnAdminIsKnownByUsername(): void
    {
        $user = DataFixtureStorageManager::getStorage()->get('user');
        self::assertNotNull($user);
        $userId = $user->getData('user_id');
        self::assertIsNumeric($userId);
        $actor = $this->resolver(UserContextInterface::USER_TYPE_ADMIN, (int) $userId)->resolve();

        self::assertNotNull($actor);
        self::assertSame(ActorType::Admin, $actor->getType());
        self::assertSame('jane.doe', $actor->getName());
        self::assertSame((string) (int) $userId, $actor->getId());
    }

    public function testAnAccountThatNoLongerExistsIsKnownByItsId(): void
    {
        $actor = $this->resolver(UserContextInterface::USER_TYPE_ADMIN, 987654)->resolve();

        self::assertNotNull($actor);
        self::assertSame('#987654', $actor->getName());
    }

    public function testNobodyAuthenticatedLeavesTheQuestionToTheNextResolver(): void
    {
        self::assertNull($this->resolver(null, null)->resolve());
    }

    public function testAGuestIsAGuest(): void
    {
        self::assertSame(
            ActorType::Guest,
            $this->resolver(UserContextInterface::USER_TYPE_GUEST, null)->resolve()?->getType()
        );
    }

    /**
     * @param int|null $userType
     * @param int|null $userId
     * @return UserContextActorResolver
     */
    private function resolver(?int $userType, ?int $userId): UserContextActorResolver
    {
        $userContext = $this->createStub(UserContextInterface::class);
        $userContext->method('getUserType')->willReturn($userType);
        $userContext->method('getUserId')->willReturn($userId);

        return self::service(UserContextActorResolver::class, ['userContext' => $userContext]);
    }

    /**
     * A service from the real container, typed
     *
     * @template T of object
     * @param class-string<T> $class
     * @param array<string,mixed> $arguments Build a fresh instance with these arguments instead
     * @return T
     */
    private static function service(string $class, array $arguments = []): object
    {
        $objectManager = Bootstrap::getObjectManager();
        $service = $arguments === [] ? $objectManager->get($class) : $objectManager->create($class, $arguments);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
