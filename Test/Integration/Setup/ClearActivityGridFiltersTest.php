<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Setup;

use Hryvinskyi\ActivityLog\Setup\Patch\Data\ClearActivityGridFilters;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The patch rewrites the stored bookmarks of the activity grid, and only those
 */
#[DbIsolation(true)]
class ClearActivityGridFiltersTest extends TestCase
{
    private const TABLE = 'ui_bookmark';

    public function testSavedViewsOfTheGridLoseTheirFiltersAndKeepTheRest(): void
    {
        $paging = ['pageSize' => 50, 'current' => 2];
        $current = $this->bookmark(ClearActivityGridFilters::NAMESPACE, 'current', [
            'current' => [
                'filters' => ['applied' => ['placeholder' => true, 'channel' => 'admin']],
                'search' => ['value' => 'jane'],
                'paging' => $paging,
            ],
        ]);
        $view = $this->bookmark(ClearActivityGridFilters::NAMESPACE, 'mine', [
            'views' => ['mine' => [
                'label' => 'Mine',
                'data' => ['filters' => ['applied' => ['created_at' => ['from' => '10/01/2026']]], 'paging' => $paging],
            ]],
        ]);
        $otherGrid = $this->bookmark('some_other_listing', 'current', [
            'current' => ['filters' => ['applied' => ['channel' => 'admin']]],
        ]);
        $unreadable = $this->bookmark(ClearActivityGridFilters::NAMESPACE, 'broken', null);

        $patch = Bootstrap::getObjectManager()->create(ClearActivityGridFilters::class);
        self::assertInstanceOf(ClearActivityGridFilters::class, $patch);
        $patch->apply();

        self::assertSame('{"current":{"paging":{"pageSize":50,"current":2}}}', $this->config($current));
        self::assertSame(
            '{"views":{"mine":{"label":"Mine","data":{"paging":{"pageSize":50,"current":2}}}}}',
            $this->config($view)
        );
        self::assertSame('{"current":{"filters":{"applied":{"channel":"admin"}}}}', $this->config($otherGrid));
        self::assertSame('{not json', $this->config($unreadable));
    }

    /**
     * Store a bookmark and return its id
     *
     * @param string $namespace
     * @param string $identifier
     * @param array<string, mixed>|null $config Null stores text that is not JSON
     * @return int
     */
    private function bookmark(string $namespace, string $identifier, ?array $config): int
    {
        $connection = $this->resource()->getConnection();
        $connection->insert($this->resource()->getTableName(self::TABLE), [
            'user_id' => $this->adminUserId(),
            'namespace' => $namespace,
            'identifier' => $identifier,
            'current' => 0,
            'config' => $config === null ? '{not json' : json_encode($config, JSON_THROW_ON_ERROR),
        ]);

        $bookmarkId = $connection->fetchOne(
            $connection->select()->from($this->resource()->getTableName(self::TABLE), ['MAX(bookmark_id)'])
        );
        self::assertIsNumeric($bookmarkId);

        return (int) $bookmarkId;
    }

    /**
     * A bookmark's stored configuration
     *
     * @param int $bookmarkId
     * @return string
     */
    private function config(int $bookmarkId): string
    {
        $connection = $this->resource()->getConnection();
        return (string) $connection->fetchOne(
            $connection->select()
                ->from($this->resource()->getTableName(self::TABLE), ['config'])
                ->where('bookmark_id = ?', $bookmarkId)
        );
    }

    /**
     * An admin account the bookmarks belong to
     *
     * @return int
     */
    private function adminUserId(): int
    {
        $connection = $this->resource()->getConnection();
        $userId = $connection->fetchOne(
            $connection->select()->from($this->resource()->getTableName('admin_user'), ['MIN(user_id)'])
        );
        self::assertIsNumeric($userId, 'The test installation has an admin account');

        return (int) $userId;
    }

    /**
     * @return ResourceConnection
     */
    private function resource(): ResourceConnection
    {
        return SearchFixture::service(ResourceConnection::class);
    }
}
