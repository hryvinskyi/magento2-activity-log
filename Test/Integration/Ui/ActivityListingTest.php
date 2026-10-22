<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Ui;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterface;
use Hryvinskyi\ActivityLog\Api\Search\ActivitySearchInterface;
use Hryvinskyi\ActivityLog\Model\Search\ActivitySearch;
use Hryvinskyi\ActivityLog\Model\Search\ChangeChannels;
use Hryvinskyi\ActivityLog\Model\Search\InvalidSearchRequestException;
use Hryvinskyi\ActivityLog\Model\Search\Query\SearchQueryBuilder;
use Hryvinskyi\ActivityLog\Model\Search\RequestCondition;
use Hryvinskyi\ActivityLog\Model\Search\SearchCounter;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Hryvinskyi\ActivityLog\Ui\DataProvider\ActivityListingDataProvider;
use Hryvinskyi\ActivityLog\Ui\DataProvider\ActivitySearchFilter;
use Hryvinskyi\ActivityLog\Ui\DataProvider\ActivitySearchParams;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\FilterPool;
use Magento\Framework\View\Element\UiComponent\DataProvider\Reporting;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The activity grid through its real listing: the data provider reads the search from the listing parameter and the
 * filter pool applies it to the grid's collection, as a render or an export does
 *
 * A change channel of the test's own is added to "changeChannels" the way a recording module's di.xml adds one; the
 * argument is restored after each test.
 */
#[AppArea('adminhtml')]
#[DbIsolation(true)]
class ActivityListingTest extends TestCase
{
    private const LISTING = 'hryvinskyi_activity_log_listing';
    private const NOW_MS = 1_792_500_000_000;
    private const MINUTE_MS = 60_000;
    private const CHANGE_CHANNEL = 'zz_grid_change';

    /**
     * Services that hold the change channels, rebuilt after the argument changes
     */
    private const HOLDERS = [
        ChangeChannels::class,
        SearchQueryBuilder::class,
        RequestCondition::class,
        ActivitySearchFilter::class,
        ActivitySearchFilter::class . '\\Proxy',
        FilterPool::class,
        Reporting::class,
        SearchCounter::class,
        ActivitySearch::class,
        ActivitySearchInterface::class,
    ];

    /**
     * @var array<mixed>
     */
    private array $originalChannels = [];

    protected function setUp(): void
    {
        $arguments = self::service(ConfigInterface::class)->getArguments(ChangeChannels::class);
        $this->originalChannels = is_array($arguments) && is_array($arguments['changeChannels'] ?? null)
            ? $arguments['changeChannels']
            : [];
        self::objectManager()->configure([ChangeChannels::class => ['arguments' => ['changeChannels' =>
            $this->originalChannels + [self::CHANGE_CHANNEL => self::CHANGE_CHANNEL]]]]);
        $this->forgetHolders();

        $fixture = new SearchFixture();
        $fixture->clear();
        $actors = ['Jane Doe', 'John Smith', 'Jane Roe'];
        $channels = [self::CHANGE_CHANNEL, 'zz_grid_api', self::CHANGE_CHANNEL, 'zz_grid_other'];
        $actions = ['Alpha saved', 'Beta saved', 'Alpha deleted', 'Gamma run', 'Beta deleted'];
        for ($i = 0; $i < 40; $i++) {
            // Two activities share each millisecond, so ties are ordered by id
            $fixture->insert(self::NOW_MS - intdiv($i, 2) * self::MINUTE_MS, [
                'actor_name' => $actors[$i % 3],
                'channel' => $channels[$i % 4],
                'action' => $actions[$i % 5],
                'correlation_id' => 'op-grid-' . intdiv($i, 3),
            ]);
        }
        $fixture->insert(self::NOW_MS - 400 * self::MINUTE_MS, [
            'actor_name' => 'Jane Doe',
            'channel' => self::CHANGE_CHANNEL,
        ]);
    }

    protected function tearDown(): void
    {
        self::objectManager()->configure([
            ChangeChannels::class => ['arguments' => ['changeChannels' => $this->originalChannels]],
        ]);
        $this->forgetHolders();
        self::request()->setParam(ActivitySearchParams::PARAM, null);
    }

    /**
     * @return array<string, array{list<string>, bool, string, string}>
     */
    public static function searchesAndOrders(): array
    {
        $searches = [
            'the range alone' => [[], false],
            'an actor, changes only' => [['by:jane'], true],
            'a channel and an actor' => [['channel:zz_grid_api', 'by:john'], false],
            'words among the capped candidates' => [['text:alpha'], false],
        ];
        $orders = [
            'newest first' => ['occurred_at_ms', 'DESC'],
            'by actor' => ['actor_name', 'ASC'],
            'by action, last first' => ['action', 'DESC'],
        ];

        $cases = [];
        foreach ($searches as $searchName => [$chips, $changesOnly]) {
            foreach ($orders as $orderName => [$field, $direction]) {
                $cases[$searchName . ', ' . $orderName] = [$chips, $changesOnly, $field, $direction];
            }
        }

        return $cases;
    }

    /**
     * @param list<string> $chips
     */
    #[DataProvider('searchesAndOrders')]
    public function testTheGridPagesThroughTheActivitiesTheEngineFinds(
        array $chips,
        bool $changesOnly,
        string $field,
        string $direction
    ): void {
        $search = $this->search(self::NOW_MS - 15 * self::MINUTE_MS, self::NOW_MS, $chips, $changesOnly);
        $engineIds = $this->engineIds($search);
        self::assertNotSame([], $engineIds, 'The search finds something to page through');

        $pages = [];
        $pageSize = 4;
        $pageCount = (int) ceil(count($engineIds) / $pageSize);
        for ($page = 1; $page <= $pageCount; $page++) {
            $data = $this->gridData($search, $page, $pageSize, $field, $direction);
            self::assertSame(count($engineIds), $data['totalRecords']);
            self::assertFalse($data['totalAtLeast']);
            $pages[] = $this->idsOf($data);
        }
        $gridIds = array_merge(...$pages);

        self::assertSame(count($gridIds), count(array_unique($gridIds)), 'No activity appears on two pages');
        if ($field === 'occurred_at_ms') {
            self::assertSame($engineIds, $gridIds, 'Newest first, both list the activities in the same order');
        }
        sort($engineIds);
        sort($gridIds);
        self::assertSame($engineIds, $gridIds);
    }

    public function testEveryFilterOfTheSearchStaysRequiredWithTheRange(): void
    {
        $search = $this->search(
            self::NOW_MS - 15 * self::MINUTE_MS,
            self::NOW_MS,
            ['channel:' . self::CHANGE_CHANNEL, 'by:roe'],
            false
        );

        $rows = $this->gridData($search, 1, 50, 'occurred_at_ms', 'DESC')['items'];

        self::assertNotSame([], $rows);
        foreach ($rows as $row) {
            self::assertIsArray($row);
            self::assertSame(
                [self::CHANGE_CHANNEL, 'Jane Roe'],
                [$row['channel'] ?? null, $row['actor_name'] ?? null],
                'A row matching the range or one filter alone is not shown'
            );
            self::assertIsNumeric($row['occurred_at_ms'] ?? null);
            self::assertGreaterThanOrEqual(self::NOW_MS - 15 * self::MINUTE_MS, (int) $row['occurred_at_ms']);
        }
    }

    public function testWithoutASearchTheGridShowsTheChangesOfTheLastSevenDays(): void
    {
        $fixture = new SearchFixture();
        $fixture->clear();
        $nowMs = (int) floor(microtime(true) * 1000);
        $recent = $fixture->insert($nowMs - 3_600_000, ['channel' => self::CHANGE_CHANNEL]);
        $fixture->insert($nowMs - 3_600_000, ['channel' => 'zz_grid_api']);
        $fixture->insert($nowMs - 8 * 86_400_000, ['channel' => self::CHANGE_CHANNEL]);

        self::assertSame([$recent], $this->idsOf($this->gridData(null, 1, 50, 'occurred_at_ms', 'DESC')));
    }

    public function testAWordSearchedAmongCappedCandidatesIsCountedAsAtLeast(): void
    {
        $fixture = new SearchFixture();
        $fixture->clear();
        $rows = [];
        for ($i = 0; $i < 10_010; $i++) {
            $rows[] = [self::NOW_MS - $i, ['action' => $i % 1000 === 0 ? 'Alpha saved' : 'Beta saved']];
        }
        $fixture->insertMany($rows);

        $data = $this->gridData($this->search(0, self::NOW_MS, ['text:alpha'], false), 1, 20, 'occurred_at_ms', 'DESC');

        self::assertSame([10, true], [$data['totalRecords'], $data['totalAtLeast']]);
        self::assertCount(10, $data['items']);
    }

    public function testAPageHoldsAtMostAsManyRowsAsASearchPageWhateverSizeIsAskedFor(): void
    {
        $fixture = new SearchFixture();
        $fixture->clear();
        $rows = [];
        for ($i = 0; $i < SearchRequestInterface::MAX_LIMIT + 50; $i++) {
            $rows[] = [self::NOW_MS - $i, ['action' => 'Alpha saved']];
        }
        $fixture->insertMany($rows);
        $search = $this->search(0, self::NOW_MS, [], false);

        $asked = $this->gridData($search, 1, 999, 'occurred_at_ms', 'DESC');
        $nothing = $this->gridData($search, 1, 0, 'occurred_at_ms', 'DESC');

        self::assertCount(SearchRequestInterface::MAX_LIMIT, $asked['items']);
        self::assertSame(SearchRequestInterface::MAX_LIMIT + 50, $asked['totalRecords']);
        self::assertCount(1, $nothing['items'], 'A page holds one row at least');
    }

    public function testAMalformedSearchIsRefusedWithItsReason(): void
    {
        $this->expectException(InvalidSearchRequestException::class);

        $this->gridData(['from' => '20', 'to' => '10'], 1, 20, 'occurred_at_ms', 'DESC');
    }

    /**
     * The grid's search parameter
     *
     * @param int $fromMs
     * @param int $toMs
     * @param list<string> $chips
     * @param bool $changesOnly
     * @return array<string, mixed>
     */
    private function search(int $fromMs, int $toMs, array $chips, bool $changesOnly): array
    {
        return [
            'from' => (string) $fromMs,
            'to' => (string) $toMs,
            'c' => $chips,
            'changes' => $changesOnly ? '1' : '0',
        ];
    }

    /**
     * Ids the search engine finds for the search, newest first, over all its pages
     *
     * @param array<string, mixed> $search
     * @return list<int>
     */
    private function engineIds(array $search): array
    {
        $params = self::service(ActivitySearchParams::class);
        $request = $params->request($params->filterValue($search))->withLimit(SearchRequestInterface::MAX_LIMIT);
        $engine = self::service(ActivitySearchInterface::class);

        $pages = [];
        do {
            $page = $engine->search($request);
            $pages[] = (new SearchFixture())->ids($page);
            $request = $request->withCursor($page->getNextCursor());
        } while ($page->getNextCursor() !== null);

        return array_merge(...$pages);
    }

    /**
     * One page of the grid as its real listing's data provider gives it
     *
     * @param array<string, mixed>|null $search The listing parameter, or null for none
     * @param int $page
     * @param int $pageSize
     * @param string $field
     * @param string $direction
     * @return array{items: list<mixed>, totalRecords: mixed, totalAtLeast: mixed}
     */
    private function gridData(?array $search, int $page, int $pageSize, string $field, string $direction): array
    {
        self::request()->setParam(ActivitySearchParams::PARAM, $search);
        $listing = self::service(UiComponentFactory::class)->create(self::LISTING);
        $dataProvider = $listing->getContext()->getDataProvider();
        self::assertInstanceOf(ActivityListingDataProvider::class, $dataProvider);
        $dataProvider->setLimit($page, $pageSize);
        $dataProvider->addOrder($field, $direction);

        $data = $dataProvider->getData();
        self::assertIsArray($data['items'] ?? null);

        return [
            'items' => array_values($data['items']),
            'totalRecords' => $data['totalRecords'] ?? null,
            'totalAtLeast' => $data['totalAtLeast'] ?? null,
        ];
    }

    /**
     * Ids of a grid page's rows, in order
     *
     * @param array{items: list<mixed>, totalRecords: mixed, totalAtLeast: mixed} $data
     * @return list<int>
     */
    private function idsOf(array $data): array
    {
        $ids = [];
        foreach ($data['items'] as $row) {
            self::assertIsArray($row);
            self::assertIsNumeric($row['entity_id'] ?? null);
            $ids[] = (int) $row['entity_id'];
        }

        return $ids;
    }

    /**
     * Drop the shared instances that hold the change channels
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
     * @return Request
     */
    private static function request(): Request
    {
        $request = Bootstrap::getObjectManager()->get(RequestInterface::class);
        self::assertInstanceOf(Request::class, $request);

        return $request;
    }

    /**
     * @return ObjectManager
     */
    private static function objectManager(): ObjectManager
    {
        $objectManager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objectManager);

        return $objectManager;
    }

    /**
     * A service from the real container, typed
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private static function service(string $class): object
    {
        return SearchFixture::service($class);
    }
}
