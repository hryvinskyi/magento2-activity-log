<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCriterionInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCursorInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchPageInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterfaceFactory;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Journal rows written straight into the table, and search requests built the way the endpoint builds them
 */
class SearchFixture
{
    /**
     * @var array<string, mixed>
     */
    private const DEFAULT_ROW = [
        'correlation_id' => 'op-search-test',
        'channel' => 'zz_test',
        'action' => 'Test action',
        'actor_type' => 'admin',
        'actor_name' => 'Tester',
        'outcome' => 'success',
    ];

    /**
     * Empty the journal; inside a test's transaction the rows come back afterwards
     *
     * @return void
     */
    public function clear(): void
    {
        $resource = $this->resource();
        $resource->getConnection()->delete($resource->getTableName(ActivityResource::TABLE));
    }

    /**
     * Write one activity and return its id
     *
     * @param int $occurredAtMs
     * @param array<string, mixed> $columns
     * @return int
     */
    public function insert(int $occurredAtMs, array $columns = []): int
    {
        $resource = $this->resource();
        $connection = $resource->getConnection();
        $table = $resource->getTableName(ActivityResource::TABLE);
        $connection->insert($table, $this->row($occurredAtMs, $columns));
        $id = $connection->fetchOne($connection->select()->from($table, ['MAX(entity_id)']));

        return is_numeric($id) ? (int) $id : 0;
    }

    /**
     * Write many activities in batches, in the order given
     *
     * @param list<array{0: int, 1: array<string, mixed>}> $rows Time and columns of each
     * @return void
     */
    public function insertMany(array $rows): void
    {
        $resource = $this->resource();
        $table = $resource->getTableName(ActivityResource::TABLE);
        foreach (array_chunk($rows, 1000) as $chunk) {
            $resource->getConnection()->insertMultiple(
                $table,
                array_map(fn (array $row): array => $this->row($row[0], $row[1]), $chunk)
            );
        }
    }

    /**
     * Ids of the journal's rows that satisfy a condition, newest first
     *
     * @param string $where
     * @return list<int>
     */
    public function idsWhere(string $where): array
    {
        $resource = $this->resource();
        $select = $resource->getConnection()->select()
            ->from($resource->getTableName(ActivityResource::TABLE), ['entity_id'])
            ->where($where)
            ->order(['occurred_at_ms DESC', 'entity_id DESC']);

        $ids = [];
        foreach ($resource->getConnection()->fetchCol($select) as $id) {
            $ids[] = is_numeric($id) ? (int) $id : 0;
        }

        return $ids;
    }

    /**
     * A request as the endpoint's parser would build it
     *
     * @param int $fromMs
     * @param int $toMs
     * @param list<array{0: string, 1: string}> $chips
     * @param bool $changesOnly
     * @param int $limit
     * @param SearchCursorInterface|null $cursor
     * @return SearchRequestInterface
     */
    public function request(
        int $fromMs,
        int $toMs,
        array $chips = [],
        bool $changesOnly = false,
        int $limit = 50,
        ?SearchCursorInterface $cursor = null
    ): SearchRequestInterface {
        $criteria = [];
        foreach ($chips as [$key, $value]) {
            $criteria[] = self::service(SearchCriterionInterfaceFactory::class)->create()->withTerm($key, $value);
        }

        return self::service(SearchRequestInterfaceFactory::class)->create()
            ->withRange($fromMs, $toMs)
            ->withCriteria($criteria)
            ->withChangesOnly($changesOnly)
            ->withLimit($limit)
            ->withCursor($cursor);
    }

    /**
     * Ids of a page's activities, in the page's order
     *
     * @param SearchPageInterface $page
     * @return list<int>
     */
    public function ids(SearchPageInterface $page): array
    {
        $ids = [];
        foreach ($page->getEvents() as $event) {
            $ids[] = $event->getActivityId();
        }

        return $ids;
    }

    /**
     * A full journal row
     *
     * @param int $occurredAtMs
     * @param array<string, mixed> $columns
     * @return array<string, mixed>
     */
    private function row(int $occurredAtMs, array $columns): array
    {
        return $columns + self::DEFAULT_ROW + [
            'occurred_at_ms' => $occurredAtMs,
            'created_at' => gmdate('Y-m-d H:i:s', intdiv($occurredAtMs, 1000)),
        ];
    }

    /**
     * @return ResourceConnection
     */
    private function resource(): ResourceConnection
    {
        return self::service(ResourceConnection::class);
    }

    /**
     * A service from the real container, typed
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     * @throws \LogicException When the container answers with something else
     */
    public static function service(string $class): object
    {
        $service = Bootstrap::getObjectManager()->get($class);
        if (!$service instanceof $class) {
            throw new \LogicException(sprintf('The container has no %s.', $class));
        }

        return $service;
    }
}
