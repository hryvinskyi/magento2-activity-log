<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Controller;

use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The search endpoint through the real admin stack; the parent class adds the ACL allow and deny tests
 */
#[DbIsolation(true)]
class ActivitySearchEndpointTest extends AbstractBackendController
{
    private const NOW_MS = 1_792_500_000_000;

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ActivityLog::view';

    /**
     * @var string
     */
    protected $uri = 'backend/activitylog/activity/search';

    /**
     * @var string
     */
    protected $httpMethod = 'GET';

    public function testAnswersWithAPageOfMatchesAndAPositionToContinueFrom(): void
    {
        $fixture = new SearchFixture();
        $fixture->clear();
        $older = $fixture->insert(self::NOW_MS - 2000, ['actor_name' => 'Jane Doe', 'channel' => 'zz_api']);
        $newer = $fixture->insert(self::NOW_MS - 1000, ['actor_name' => 'Jane Doe', 'channel' => 'zz_api']);
        $fixture->insert(self::NOW_MS - 500, ['actor_name' => 'Somebody Else']);

        $this->httpRequest()->setMethod('GET')->setParams([
            'from' => '0',
            'to' => (string) self::NOW_MS,
            'c' => ['by:jane'],
            'limit' => '1',
        ]);
        $this->dispatch($this->uri);
        $first = $this->json(200);

        $events = is_array($first['events'] ?? null) ? $first['events'] : [];
        self::assertCount(1, $events);
        self::assertIsArray($events[0]);
        self::assertSame(
            [$newer, self::NOW_MS - 1000, 'zz_api'],
            [$events[0]['id'] ?? null, $events[0]['occurredAtMs'] ?? null, $events[0]['channel'] ?? null]
        );
        self::assertIsString($first['nextCursor'] ?? null);
        self::assertSame(['count' => 2, 'atLeast' => false], $first['total'] ?? null);
        self::assertSame(['count' => 0, 'atLeast' => false], $first['outside'] ?? null);
        self::assertSame(['count' => 2, 'atLeast' => false], $first['overall'] ?? null);
        self::assertSame([false, false], [$first['narrowed'] ?? null, $first['journalEmpty'] ?? null]);
        self::assertStringContainsString('no-store', $this->header('Cache-Control'));

        $this->resetRequest();
        // Every result carries a link to its detail page, and building an admin link with parameters switches the
        // shared URL model's secret key back on; the test's request URIs carry no key
        $backendUrl = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->get(\Magento\Backend\Model\UrlInterface::class);
        self::assertInstanceOf(\Magento\Backend\Model\UrlInterface::class, $backendUrl);
        $backendUrl->turnOffSecretKey();
        $this->httpRequest()->setMethod('GET')->setParams([
            'from' => '0',
            'to' => (string) self::NOW_MS,
            'c' => ['by:jane'],
            'limit' => '1',
            'cursor' => $first['nextCursor'],
        ]);
        $this->dispatch($this->uri);
        $second = $this->json(200);

        self::assertSame([$older], array_column(is_array($second['events'] ?? null) ? $second['events'] : [], 'id'));
        self::assertNull($second['nextCursor'] ?? null);
        self::assertNull($second['total'] ?? null, 'Counts come with the first page only');
    }

    public function testFindsAnOperationWholeTheWayTheTimelineAsksForItsOtherActivities(): void
    {
        $fixture = new SearchFixture();
        $fixture->clear();
        $dayMs = 86_400_000;
        $shown = $fixture->insert(self::NOW_MS - 1000, ['correlation_id' => 'op-timeline', 'channel' => 'zz_test']);
        $before = $fixture->insert(
            self::NOW_MS - 40 * $dayMs,
            ['correlation_id' => 'op-timeline', 'channel' => 'zz_other']
        );
        $alone = $fixture->insert(self::NOW_MS - 500, ['correlation_id' => 'op-alone', 'channel' => 'zz_test']);

        // The search the timeline shows: the last seven days
        $this->httpRequest()->setMethod('GET')->setParams([
            'from' => (string) (self::NOW_MS - 7 * $dayMs),
            'to' => (string) self::NOW_MS,
            'changes' => '0',
            'limit' => '50',
        ]);
        $this->dispatch($this->uri);
        $page = $this->json(200);
        $events = is_array($page['events'] ?? null) ? $page['events'] : [];

        self::assertSame([$alone, $shown], array_column($events, 'id'));
        self::assertSame(
            [0, 1],
            array_column($events, 'siblingCount'),
            'the operation\'s activity outside the range is counted with it'
        );

        $this->resetRequest();
        $backendUrl = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->get(\Magento\Backend\Model\UrlInterface::class);
        self::assertInstanceOf(\Magento\Backend\Model\UrlInterface::class, $backendUrl);
        $backendUrl->turnOffSecretKey();

        // "1 more in this request": the whole log, every channel, at most 200 activities
        $this->httpRequest()->setMethod('GET')->setParams([
            'from' => '0',
            'to' => (string) self::NOW_MS,
            'c' => ['request:op-timeline'],
            'changes' => '0',
            'limit' => '200',
        ]);
        $this->dispatch($this->uri);
        $operation = $this->json(200);

        self::assertSame(
            [$shown, $before],
            array_column(is_array($operation['events'] ?? null) ? $operation['events'] : [], 'id')
        );
        self::assertNull($operation['nextCursor'] ?? null);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function refused(): array
    {
        return [
            'no range' => [[]],
            'twenty-one filters' => [['from' => '0', 'to' => '10', 'c' => array_fill(0, 21, 'text:x')]],
            'an altered cursor' => [['from' => '0', 'to' => '10', 'cursor' => 'MTIzLjQ1Ni5hYmNkZWYwMQ~~']],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refused')]
    public function testRefusesAMalformedSearchWithAReason(array $params): void
    {
        $this->httpRequest()->setMethod('GET')->setParams($params);
        $this->dispatch($this->uri);

        $body = $this->json(400);
        self::assertIsString($body['error'] ?? null);
        self::assertNotSame('', $body['error']);
    }

    /**
     * The request, typed
     *
     * @return \Magento\TestFramework\Request
     */
    private function httpRequest(): \Magento\TestFramework\Request
    {
        $request = $this->getRequest();
        self::assertInstanceOf(\Magento\TestFramework\Request::class, $request);

        return $request;
    }

    /**
     * The response body as JSON, after checking the status
     *
     * @param int $status
     * @return array<string, mixed>
     */
    private function json(int $status): array
    {
        $response = $this->getResponse();
        self::assertInstanceOf(\Magento\TestFramework\Response::class, $response);
        self::assertSame($status, $response->getHttpResponseCode());
        $body = json_decode((string) $response->getBody(), true);
        self::assertIsArray($body);

        /** @var array<string, mixed> $body */
        return $body;
    }

    /**
     * A response header's value, or '' when absent
     *
     * @param string $name
     * @return string
     */
    private function header(string $name): string
    {
        $response = $this->getResponse();
        self::assertInstanceOf(\Magento\TestFramework\Response::class, $response);
        $header = $response->getHeader($name);

        return $header instanceof \Laminas\Http\Header\HeaderInterface ? $header->getFieldValue() : '';
    }
}
