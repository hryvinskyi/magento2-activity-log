<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Controller;

use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Hryvinskyi\ActivityLog\Ui\DataProvider\ActivitySearchParams;
use Laminas\Http\Headers;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Form\FormKey;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Request;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The activity grid through the real admin stack: a render of its listing and a mass action over its search; the
 * parent class adds the ACL allow and deny tests of the render
 */
#[DbIsolation(true)]
class ActivityGridTest extends AbstractBackendController
{
    private const LISTING = 'hryvinskyi_activity_log_listing';
    private const NOW_MS = 1_792_500_000_000;

    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ActivityLog::view';

    /**
     * @var string
     */
    protected $uri = 'backend/mui/index/render';

    /**
     * @var string
     */
    protected $httpMethod = 'GET';

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpRequest()->setParam('namespace', self::LISTING);
        $this->httpRequest()->setHeaders(Headers::fromString('Accept: application/json'));
    }

    public function testARenderedGridCountsTenThousandAndNoFurther(): void
    {
        $fixture = new SearchFixture();
        $fixture->clear();
        $rows = [];
        for ($i = 0; $i < 10_005; $i++) {
            $rows[] = [self::NOW_MS - $i, ['channel' => 'zz_many']];
        }
        $fixture->insertMany($rows);
        $connection = SearchFixture::service(ResourceConnection::class)->getConnection();
        self::assertInstanceOf(\Zend_Db_Adapter_Abstract::class, $connection);
        $journal = SearchFixture::service(ResourceConnection::class)->getTableName(ActivityResource::TABLE);
        $profiler = $connection->getProfiler();
        $profiler->setEnabled(true);
        $profiler->clear();

        $this->httpRequest()->setMethod('GET')->setParams([
            ActivitySearchParams::PARAM => [
                'from' => '0',
                'to' => (string) self::NOW_MS,
                'c' => ['channel:zz_many'],
                'changes' => '0',
            ],
            'paging' => ['pageSize' => '20', 'current' => '1'],
        ]);
        try {
            $this->dispatch($this->uri);
            $queries = [];
            foreach ($profiler->getQueryProfiles() ?: [] as $profile) {
                self::assertInstanceOf(\Zend_Db_Profiler_Query::class, $profile);
                $queries[] = (string) $profile->getQuery();
            }
        } finally {
            $profiler->setEnabled(false);
        }

        $response = $this->getResponse();
        self::assertInstanceOf(\Magento\TestFramework\Response::class, $response);
        $body = json_decode((string) $response->getBody(), true);
        self::assertIsArray($body);
        self::assertSame([10_000, true], [$body['totalRecords'] ?? null, $body['totalAtLeast'] ?? null]);
        self::assertIsArray($body['items'] ?? null);
        self::assertCount(20, $body['items']);
        self::assertNotSame(
            [],
            array_filter($queries, static fn (string $query): bool => str_contains($query, 'LIMIT 10001')),
            'The count reads one row past the bound'
        );
        foreach ($queries as $query) {
            self::assertDoesNotMatchRegularExpression(
                '/^SELECT COUNT\(\*\) FROM `' . preg_quote($journal, '/') . '` AS `main_table`/',
                $query,
                'The grid never counts every match'
            );
        }
    }

    public function testSelectingAllDeletesTheActivitiesOfTheSearchAndNoOthers(): void
    {
        $fixture = new SearchFixture();
        $fixture->clear();
        $searched = [
            $fixture->insert(self::NOW_MS - 1000, ['channel' => 'zz_delete', 'actor_name' => 'Jane Doe']),
            $fixture->insert(self::NOW_MS - 2000, ['channel' => 'zz_delete', 'actor_name' => 'Jane Doe']),
        ];
        $kept = [
            $fixture->insert(self::NOW_MS - 1500, ['channel' => 'zz_delete', 'actor_name' => 'John Smith']),
            $fixture->insert(self::NOW_MS - 1500, ['channel' => 'zz_keep', 'actor_name' => 'Jane Doe']),
        ];

        $this->httpRequest()->setMethod('POST')->setPostValue([
            'namespace' => self::LISTING,
            'excluded' => 'false',
            ActivitySearchParams::PARAM => [
                'from' => '0',
                'to' => (string) self::NOW_MS,
                'c' => ['channel:zz_delete', 'by:jane'],
                'changes' => '0',
            ],
            'form_key' => SearchFixture::service(FormKey::class)->getFormKey(),
        ]);
        $this->dispatch('backend/activitylog/activity/massDelete');

        self::assertSame([], array_values(array_intersect($searched, $this->journalIds())));
        self::assertSame($kept, array_values(array_intersect($kept, $this->journalIds())));
    }

    /**
     * The request, typed
     *
     * @return Request
     */
    private function httpRequest(): Request
    {
        $request = $this->getRequest();
        self::assertInstanceOf(Request::class, $request);

        return $request;
    }

    /**
     * Ids the journal holds
     *
     * @return list<int>
     */
    private function journalIds(): array
    {
        return (new SearchFixture())->idsWhere('1 = 1');
    }
}
