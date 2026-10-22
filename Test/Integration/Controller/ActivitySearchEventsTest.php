<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Controller;

use Hryvinskyi\ActivityLog\Api\Data\Search\FieldChangeInterface;
use Hryvinskyi\ActivityLog\Model\Search\ActivitySearch;
use Hryvinskyi\ActivityLog\Model\Search\Detail\DetailProviderPool;
use Hryvinskyi\ActivityLog\Model\Search\Detail\EventDetails;
use Hryvinskyi\ActivityLog\Model\Search\EventViewAssembler;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\FailingDetailProvider;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\FixedDetailProvider;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * What each search result carries, through the real admin stack: the journal's facts, the size of its operation,
 * the details a registered provider gives (values capped for transport) and the link to its detail page
 *
 * Two test providers are added to the "detailProviders" argument the way a module's di.xml adds them; the argument
 * is restored after each test.
 */
#[DbIsolation(true)]
class ActivitySearchEventsTest extends AbstractBackendController
{
    private const NOW_MS = 1_792_500_000_000;
    private const FIXED = 'zz_test_fixed_details';
    private const FAILING = 'zz_test_failing_details';

    /**
     * Services holding the provider pool, rebuilt after its argument changes
     */
    private const HOLDERS = [
        DetailProviderPool::class,
        EventDetails::class,
        EventViewAssembler::class,
        ActivitySearch::class,
    ];

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

    /**
     * @var array<mixed>
     */
    private array $originalProviders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $config = self::objectManager()->get(ConfigInterface::class);
        self::assertInstanceOf(ConfigInterface::class, $config);
        $arguments = $config->getArguments(DetailProviderPool::class);
        $this->originalProviders = is_array($arguments) && is_array($arguments['detailProviders'] ?? null)
            ? $arguments['detailProviders']
            : [];

        self::objectManager()->configure([
            self::FIXED => ['type' => FixedDetailProvider::class, 'arguments' => ['channel' => 'zz_detail']],
            self::FAILING => ['type' => FailingDetailProvider::class, 'arguments' => ['channel' => 'zz_broken']],
            DetailProviderPool::class => ['arguments' => ['detailProviders' => $this->originalProviders + [
                self::FAILING => ['instance' => self::FAILING],
                self::FIXED => ['instance' => self::FIXED],
            ]]],
        ]);
        $this->forgetHolders();
    }

    protected function tearDown(): void
    {
        self::objectManager()->configure([
            DetailProviderPool::class => ['arguments' => ['detailProviders' => $this->originalProviders]],
        ]);
        $this->forgetHolders();
        self::objectManager()->removeSharedInstance(self::FIXED);
        self::objectManager()->removeSharedInstance(self::FAILING);

        parent::tearDown();
    }

    public function testAnEventCarriesTheJournalItsOperationAndItsDetailPage(): void
    {
        $fixture = new SearchFixture();
        $fixture->clear();
        $fixture->insert(self::NOW_MS - 3_600_000, ['correlation_id' => 'op-events-0001', 'channel' => 'zz_other']);
        $fixture->insert(self::NOW_MS - 2000, ['correlation_id' => 'op-events-0001', 'channel' => 'zz_other']);
        $id = $fixture->insert(self::NOW_MS - 1000, [
            'correlation_id' => 'op-events-0001',
            'channel' => 'zz_plain',
            'action' => 'Cache flushed',
            'actor_type' => 'integration',
            'actor_name' => 'Sync job',
            'via_client' => 'Agent',
            'via_version' => '1.2',
            'via_channel' => 'zz_agents',
            'outcome' => 'failure',
            'message' => 'Partly flushed',
            'duration_ms' => 12.5,
            'ip' => '10.0.0.1',
            'subject_type' => 'cache',
            'subject_id' => 'full_page',
            'subject_label' => 'Page cache',
        ]);

        $events = $this->search(['from' => (string) (self::NOW_MS - 60_000), 'to' => (string) self::NOW_MS]);
        $event = $events[0] ?? [];

        self::assertSame([$id, self::NOW_MS - 1000, 'zz_plain', 'op-events-0001'], [
            $event['id'] ?? null,
            $event['occurredAtMs'] ?? null,
            $event['channel'] ?? null,
            $event['correlationId'] ?? null,
        ]);
        self::assertSame(
            2,
            $event['siblingCount'] ?? null,
            'Both other activities of the operation count, the one outside the range too'
        );
        self::assertSame(
            [
                'type' => 'integration',
                'name' => 'Sync job',
                'viaClient' => 'Agent',
                'viaVersion' => '1.2',
                'viaChannel' => 'zz_agents',
            ],
            $event['actor'] ?? null,
            'the channel the client acted through travels as its code, registered or not'
        );
        self::assertSame(['Cache flushed', 'failure', 'Partly flushed', 12.5, '10.0.0.1'], [
            $event['action'] ?? null,
            $event['outcome'] ?? null,
            $event['message'] ?? null,
            $event['durationMs'] ?? null,
            $event['ip'] ?? null,
        ]);
        self::assertSame(['type' => 'cache', 'id' => 'full_page', 'label' => 'Page cache'], $event['subject'] ?? null);
        self::assertSame(['performed', null], [$event['verb'] ?? null, self::present($event, 'detail')]);
        self::assertIsString($event['detailUrl'] ?? null);
        self::assertStringContainsString('activitylog/activity/view/id/' . $id . '/', $event['detailUrl']);
    }

    public function testARegisteredProviderDescribesItsActivitiesInOneCallWithValuesCappedForTransport(): void
    {
        $fixture = new SearchFixture();
        $fixture->clear();
        $first = $fixture->insert(self::NOW_MS - 3000, ['channel' => 'zz_detail']);
        $plain = $fixture->insert(self::NOW_MS - 2000, ['channel' => 'zz_plain']);
        $second = $fixture->insert(
            self::NOW_MS - 1000,
            ['channel' => 'zz_detail', 'subject_type' => 'thing', 'subject_label' => 'Thing Bag']
        );

        $events = $this->search(['from' => '0', 'to' => (string) self::NOW_MS]);

        $provider = self::objectManager()->get(self::FIXED);
        self::assertInstanceOf(FixedDetailProvider::class, $provider);
        self::assertSame([[$second, $first]], $provider->calls, 'One call for the page, newest first');
        self::assertSame([[$second => 'Thing Bag']], $provider->labels, 'With the names the journal recorded');

        self::assertSame([$second, $plain, $first], array_column($events, 'id'));
        self::assertArrayHasKey('detail', $events[1]);
        self::assertNull($events[1]['detail']);
        self::assertSame('changed', $events[0]['verb'] ?? null);

        $detail = $events[0]['detail'] ?? null;
        self::assertIsArray($detail);
        self::assertSame([null, 4, 'Thing', 'Bag', '/admin/thing/edit/id/' . $second . '/', 1, 2, true], [
            self::present($detail, 'what'),
            $detail['fieldCount'] ?? null,
            $detail['subjectTypeLabel'] ?? null,
            $detail['subjectLabel'] ?? null,
            $detail['subjectUrl'] ?? null,
            $detail['moreFieldCount'] ?? null,
            $detail['unchangedCount'] ?? null,
            $detail['unchangedRecorded'] ?? null,
        ]);

        $fields = is_array($detail['fields'] ?? null) ? $detail['fields'] : [];
        self::assertSame([
            'name' => 'price',
            'label' => 'Price',
            'scope' => 'Default Store View',
            'before' => '34.00',
            'after' => '39.00',
            'kind' => 'short',
            'changed' => true,
            'automatic' => false,
            'truncated' => false,
            'beforeLength' => 5,
            'afterLength' => 5,
        ], $fields[0] ?? null);

        $long = is_array($fields[1] ?? null) ? $fields[1] : [];
        self::assertSame(['long', true, FixedDetailProvider::LONG_VALUE_LENGTH, 9], [
            $long['kind'] ?? null,
            $long['truncated'] ?? null,
            $long['beforeLength'] ?? null,
            $long['afterLength'] ?? null,
        ]);
        self::assertIsString($long['before'] ?? null);
        self::assertSame(FieldChangeInterface::VALUE_LIMIT, strlen($long['before']));

        $masked = is_array($fields[2] ?? null) ? $fields[2] : [];
        self::assertSame(['api_secret', 'masked'], [$masked['label'] ?? null, $masked['kind'] ?? null]);
    }

    public function testAFailingProviderLeavesItsActivitiesToTheJournalAndOthersStillDescribed(): void
    {
        $fixture = new SearchFixture();
        $fixture->clear();
        $described = $fixture->insert(self::NOW_MS - 2000, ['channel' => 'zz_detail']);
        $broken = $fixture->insert(self::NOW_MS - 1000, ['channel' => 'zz_broken', 'action' => 'Synced']);

        $events = $this->search(['from' => '0', 'to' => (string) self::NOW_MS]);

        self::assertSame([$broken, $described], array_column($events, 'id'));
        self::assertSame(['performed', null, 'Synced'], [
            $events[0]['verb'] ?? null,
            self::present($events[0] ?? [], 'detail'),
            $events[0]['action'] ?? null,
        ]);
        self::assertIsArray($events[1]['detail'] ?? null);
    }

    /**
     * The events of a first page for these query parameters
     *
     * @param array<string, string> $params
     * @return list<array<string, mixed>>
     */
    private function search(array $params): array
    {
        $request = $this->getRequest();
        self::assertInstanceOf(\Magento\TestFramework\Request::class, $request);
        $request->setMethod('GET')->setParams($params);
        $this->dispatch($this->uri);

        $response = $this->getResponse();
        self::assertInstanceOf(\Magento\TestFramework\Response::class, $response);
        self::assertSame(200, $response->getHttpResponseCode(), (string) $response->getBody());
        $body = json_decode((string) $response->getBody(), true);
        self::assertIsArray($body);
        self::assertIsArray($body['events'] ?? null);

        $events = [];
        foreach ($body['events'] as $event) {
            self::assertIsArray($event);
            /** @var array<string, mixed> $event */
            $events[] = $event;
        }

        return $events;
    }

    /**
     * A key's value, null included, or a marker when the key is absent
     *
     * @param array<mixed> $data
     * @param string $key
     * @return mixed
     */
    private static function present(array $data, string $key): mixed
    {
        return array_key_exists($key, $data) ? $data[$key] : 'absent';
    }

    /**
     * Drop the shared services that hold the provider pool, so the next request builds them from the arguments
     *
     * @return void
     */
    private function forgetHolders(): void
    {
        foreach (self::HOLDERS as $holder) {
            self::objectManager()->removeSharedInstance($holder);
        }
    }

    /**
     * The test framework's container
     *
     * @return ObjectManager
     */
    private static function objectManager(): ObjectManager
    {
        $objectManager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objectManager);

        return $objectManager;
    }
}
