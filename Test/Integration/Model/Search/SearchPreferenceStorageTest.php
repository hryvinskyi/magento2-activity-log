<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search;

use Hryvinskyi\ActivityLog\Model\Dashboard\BookmarkPreferenceStorage;
use Hryvinskyi\ActivityLog\Model\Search\Preference\BookmarkSearchPreferenceStorage;
use Hryvinskyi\ActivityLog\Model\Search\Preference\ResultView;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\FixedUserContext;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The search page's and the dashboard's choices in the real bookmark table: each page keeps its own, per admin user
 */
#[DbIsolation(true)]
class SearchPreferenceStorageTest extends TestCase
{
    public function testThePagesKeepTheirChoicesApartAndPerAdminUser(): void
    {
        $userId = $this->adminUserId();
        $search = $this->searchStorage($userId);
        $dashboard = $this->dashboardStorage($userId);

        self::assertSame(['view' => null, 'changesOnly' => null], $search->load(), 'nothing chosen yet');

        $search->save(ResultView::Grid, false);
        $dashboard->save(30, ['admin']);
        self::assertSame(['view' => ResultView::Grid, 'changesOnly' => false], $search->load());
        self::assertSame(['periodDays' => 30, 'channels' => ['admin']], $dashboard->load());

        $search->save(ResultView::Timeline, true);
        self::assertSame(['view' => ResultView::Timeline, 'changesOnly' => true], $search->load(), 'replaced');
        self::assertSame(['periodDays' => 30, 'channels' => ['admin']], $dashboard->load(), 'the dashboard\'s stays');

        self::assertSame(['view' => null, 'changesOnly' => null], $this->searchStorage(null)->load());
        $this->searchStorage(null)->save(ResultView::Grid, true);
        self::assertSame(
            ['view' => ResultView::Timeline, 'changesOnly' => true],
            $search->load(),
            'nothing is kept without a signed-in admin user'
        );
    }

    public function testSavingKeepsOneBookmarkPerPageAndFoldsADuplicateAnEarlierRaceLeftIntoIt(): void
    {
        $userId = $this->adminUserId();
        $bookmarks = FixedUserContext::bookmarksOf($userId);
        $namespace = 'zz_test_preference_page';

        $bookmarks->save($namespace, 'Test page', ['n' => 1]);
        $bookmarks->save($namespace, 'Test page', ['n' => 2]);
        self::assertSame([['n' => 2]], $this->kept($userId, $namespace), 'Two saves, one bookmark');

        $resource = SearchFixture::service(ResourceConnection::class);
        $resource->getConnection()->insert($resource->getTableName('ui_bookmark'), [
            'user_id' => $userId,
            'namespace' => $namespace,
            'identifier' => 'current',
            'current' => 1,
            'title' => 'Test page',
            'config' => '{"n":0}',
        ]);
        self::assertCount(2, $this->kept($userId, $namespace));

        $bookmarks->save($namespace, 'Test page', ['n' => 3]);
        self::assertSame([['n' => 3]], $this->kept($userId, $namespace));
        self::assertSame(['n' => 3], $bookmarks->load($namespace));
    }

    /**
     * The configs of the bookmarks an admin user keeps for a page, as stored, the oldest first
     *
     * @param int $userId
     * @param string $namespace
     * @return list<mixed>
     */
    private function kept(int $userId, string $namespace): array
    {
        $resource = SearchFixture::service(ResourceConnection::class);
        $connection = $resource->getConnection();

        return array_map(
            static fn (mixed $config): mixed => json_decode(is_string($config) ? $config : 'null', true),
            array_values($connection->fetchCol(
                $connection->select()
                    ->from($resource->getTableName('ui_bookmark'), ['config'])
                    ->where('user_id = ?', $userId)
                    ->where('namespace = ?', $namespace)
                    ->where('identifier = ?', 'current')
                    ->order('bookmark_id ASC')
            ))
        );
    }

    /**
     * @param int|null $userId
     * @return BookmarkSearchPreferenceStorage
     */
    private function searchStorage(?int $userId): BookmarkSearchPreferenceStorage
    {
        return $this->create(
            BookmarkSearchPreferenceStorage::class,
            ['bookmarkConfig' => FixedUserContext::bookmarksOf($userId)]
        );
    }

    /**
     * @param int|null $userId
     * @return BookmarkPreferenceStorage
     */
    private function dashboardStorage(?int $userId): BookmarkPreferenceStorage
    {
        return $this->create(
            BookmarkPreferenceStorage::class,
            ['bookmarkConfig' => FixedUserContext::bookmarksOf($userId)]
        );
    }

    /**
     * A new instance from the container, with the given arguments
     *
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $arguments
     * @return T
     */
    private function create(string $class, array $arguments): object
    {
        $instance = Bootstrap::getObjectManager()->create($class, $arguments);
        self::assertInstanceOf($class, $instance);

        return $instance;
    }

    /**
     * @return int An admin user the bookmarks can belong to
     */
    private function adminUserId(): int
    {
        $resource = SearchFixture::service(ResourceConnection::class);
        $connection = $resource->getConnection();
        $userId = $connection->fetchOne(
            $connection->select()->from($resource->getTableName('admin_user'), ['user_id'])->limit(1)
        );
        self::assertIsNumeric($userId);

        return (int) $userId;
    }
}
