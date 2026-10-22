<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture;

use Hryvinskyi\ActivityLog\Model\Preference\BookmarkConfig;
use Hryvinskyi\ActivityLog\Model\Search\Preference\BookmarkSearchPreferenceStorage;
use Magento\Authorization\Model\CompositeUserContext;
use Magento\Authorization\Model\UserContextInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\Ui\Api\BookmarkManagementInterface;
use Magento\Ui\Model\BookmarkManagement;

/**
 * An admin user signed in, or nobody, for code that asks who is
 */
class FixedUserContext implements UserContextInterface
{
    /**
     * @param int|null $userId
     */
    public function __construct(private readonly ?int $userId)
    {
    }

    /**
     * The bookmarks of that admin user, read and written through the real bookmark table
     *
     * @param int|null $userId
     * @return BookmarkConfig
     */
    public static function bookmarksOf(?int $userId): BookmarkConfig
    {
        $objectManager = Bootstrap::getObjectManager();
        $bookmarks = $objectManager->create(BookmarkConfig::class, [
            'userContext' => new self($userId),
            'bookmarkManagement' => $objectManager->create(
                BookmarkManagementInterface::class,
                ['userContext' => new self($userId)]
            ),
        ]);
        if (!$bookmarks instanceof BookmarkConfig) {
            throw new \LogicException('The container has no bookmark config.');
        }

        return $bookmarks;
    }

    /**
     * Makes the container's user context this admin user, and the bookmark services read it
     *
     * The container's own user context is built once, before a test's request sets up the admin session, and the
     * bookmark services keep it; a test of what an admin user's bookmarks do stands in for it.
     *
     * @param ObjectManager $objectManager
     * @param int $userId
     * @return void
     */
    public static function signIn(ObjectManager $objectManager, int $userId): void
    {
        self::forgetServices($objectManager);
        $objectManager->addSharedInstance(new self($userId), CompositeUserContext::class);
    }

    /**
     * Gives the container its own user context back
     *
     * @param ObjectManager $objectManager
     * @return void
     */
    public static function signOut(ObjectManager $objectManager): void
    {
        self::forgetServices($objectManager);
        $objectManager->removeSharedInstance(CompositeUserContext::class);
    }

    /**
     * @param ObjectManager $objectManager
     * @return void
     */
    private static function forgetServices(ObjectManager $objectManager): void
    {
        foreach ([BookmarkManagement::class, BookmarkConfig::class, BookmarkSearchPreferenceStorage::class] as $class) {
            $objectManager->removeSharedInstance($class);
        }
    }

    /**
     * @inheritDoc
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @return int
     */
    public function getUserType()
    {
        return UserContextInterface::USER_TYPE_ADMIN;
    }
}
