<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Controller;

use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The activity detail page and deleting from it, through the real admin stack
 */
class ActivityViewTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ActivityLog::view';

    /**
     * @var string
     */
    protected $uri = 'backend/activitylog/activity/view/id/1';

    public function testShowsTheActivityAndTheRestOfItsOperation(): void
    {
        $trigger = $this->insert('op-view0001', 'mcp', 'execute_ability cms.block_repository.save', 1000);
        $change = $this->insert('op-view0001', 'entity', 'Block saved', 1038);

        $this->dispatch('backend/activitylog/activity/view/id/' . $change);
        $html = $this->body();

        self::assertStringContainsString('Block saved', $html);
        self::assertStringContainsString('Same operation', $html);
        self::assertStringContainsString('execute_ability cms.block_repository.save', $html);
        self::assertStringContainsString('activitylog/activity/view/id/' . $trigger, $html);
        self::assertStringContainsString('+38 ms', $html);
        foreach (['arrow-left', 'trash-2', 'link'] as $icon) {
            self::assertStringContainsString('class="activity-button__icon lucide lucide-' . $icon . '"', $html);
        }
        self::assertStringContainsString('class="activity-timeline__open-icon lucide lucide-arrow-right"', $html);
        self::assertStringContainsString('class="menu-item-icon lucide lucide-layout-dashboard"', $html);
    }

    public function testSaysWhenTheActivityIsGone(): void
    {
        $this->dispatch('backend/activitylog/activity/view/id/987654321');

        self::assertStringContainsString('no longer exists', $this->body());
    }

    public function testDeletingKeepsTheRestOfTheOperation(): void
    {
        $kept = $this->insert('op-view0002', 'mcp', 'execute_ability cms.block_repository.save', 1000);
        $deleted = $this->insert('op-view0002', 'entity', 'Block saved', 1038);

        $this->httpRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->dispatch('backend/activitylog/activity/delete/id/' . $deleted);

        self::assertSame([$kept], $this->remaining([$kept, $deleted]));
    }

    /**
     * @param string $correlationId
     * @param string $channel
     * @param string $action
     * @param int $occurredAtMs
     * @return int
     */
    private function insert(string $correlationId, string $channel, string $action, int $occurredAtMs): int
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        self::assertInstanceOf(ResourceConnection::class, $resource);
        $connection = $resource->getConnection();
        $table = $resource->getTableName(ActivityResource::TABLE);
        $connection->insert($table, [
            'correlation_id' => $correlationId,
            'channel' => $channel,
            'action' => $action,
            'actor_type' => 'admin',
            'actor_name' => 'jane.doe',
            'outcome' => 'success',
            'occurred_at_ms' => 1792500000000 + $occurredAtMs,
            'created_at' => '2026-10-20 12:00:00',
        ]);
        $id = $connection->fetchOne(
            $connection->select()
                ->from($table, ['MAX(entity_id)'])
                ->where('correlation_id = ?', $correlationId)
        );
        self::assertIsNumeric($id);

        return (int) $id;
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function remaining(array $ids): array
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        self::assertInstanceOf(ResourceConnection::class, $resource);
        $connection = $resource->getConnection();
        $found = [];
        foreach ($connection->fetchCol(
            $connection->select()
                ->from($resource->getTableName(ActivityResource::TABLE), ['entity_id'])
                ->where('entity_id IN (?)', $ids)
        ) as $id) {
            if (is_numeric($id)) {
                $found[] = (int) $id;
            }
        }

        sort($found);

        return $found;
    }

    /**
     * @return \Magento\TestFramework\Request
     */
    private function httpRequest(): \Magento\TestFramework\Request
    {
        $request = $this->getRequest();
        self::assertInstanceOf(\Magento\TestFramework\Request::class, $request);

        return $request;
    }

    /**
     * @return string
     */
    private function body(): string
    {
        $response = $this->getResponse();
        self::assertInstanceOf(\Magento\TestFramework\Response::class, $response);

        return (string) $response->getBody();
    }
}
