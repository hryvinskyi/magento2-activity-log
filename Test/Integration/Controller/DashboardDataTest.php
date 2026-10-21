<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Controller;

use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The dashboard data endpoint through the real admin stack; the parent class adds the ACL allow/deny tests
 */
class DashboardDataTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ActivityLog::dashboard';

    /**
     * @var string
     */
    protected $uri = 'backend/activitylog/dashboard/data';

    public function testAnswersWithTheSummaryAndEveryWidget(): void
    {
        $this->httpRequest()->setParam('period', '7');
        $this->dispatch($this->uri);

        $body = json_decode($this->body(), true);

        self::assertIsArray($body);
        self::assertSame(7, $body['periodDays'] ?? null);
        self::assertIsArray($body['summary'] ?? null);
        self::assertCount(4, $body['summary']);
        self::assertIsArray($body['widgets'] ?? null);
        self::assertSame(['volume', 'slowest_actions', 'top_actors'], array_keys($body['widgets']));
        foreach ($body['widgets'] as $code => $widget) {
            self::assertIsArray($widget);
            self::assertSame('ok', $widget['status'] ?? null, (string) $code);
        }
    }

    public function testFallsBackToThirtyDaysForAPeriodItDoesNotOffer(): void
    {
        $this->httpRequest()->setParam('period', '365');
        $this->dispatch($this->uri);

        $body = json_decode($this->body(), true);

        self::assertIsArray($body);
        self::assertSame(30, $body['periodDays'] ?? null);
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
