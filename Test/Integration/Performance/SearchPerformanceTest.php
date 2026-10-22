<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Performance;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterface;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity as ActivityResource;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity\Grid\Collection as GridCollection;
use Hryvinskyi\ActivityLog\Model\Search\ActivitySearch;
use Hryvinskyi\ActivityLog\Model\Search\ChangeChannels;
use Hryvinskyi\ActivityLog\Model\Search\ConditionPool;
use Hryvinskyi\ActivityLog\Model\Search\CriteriaResolver;
use Hryvinskyi\ActivityLog\Model\Search\Query\SearchQueryBuilder;
use Hryvinskyi\ActivityLog\Model\Search\Query\TimeScope;
use Hryvinskyi\ActivityLog\Model\Search\RequestCondition;
use Hryvinskyi\ActivityLog\Model\Search\SearchCounter;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Hryvinskyi\ActivityLog\Test\Integration\Performance\Fixture\FieldPathCondition;
use Hryvinskyi\ActivityLog\Test\Integration\Performance\Fixture\FieldTextCondition;
use Hryvinskyi\ActivityLog\Test\Integration\Performance\Fixture\PerformanceJournal;
use Hryvinskyi\ActivityLog\Test\Integration\Performance\Fixture\RecordCondition;
use Hryvinskyi\ActivityLog\Ui\DataProvider\ActivitySearchFilter;
use Hryvinskyi\ActivityLog\Ui\DataProvider\ActivitySearchParams;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The search shapes the log page, the record panels and the setting history send, over a million activities
 *
 * Each shape is a whole search — the page and every count it carries — timed as the median of five runs after a
 * warm-up, and must answer within BUDGET_MS; the worst plain text a person can send, eight one-letter words, within
 * WORST_TEXT_BUDGET_MS. The page query's plan and the timings are written to standard error so a run shows what the
 * database did. The grid's shapes are a page of the grid's collection with the search
 * applied, sorted by a column with an index and by columns without one, read with its computed columns and its count
 * up to the bound.
 */
#[DbIsolation(false)]
class SearchPerformanceTest extends TestCase
{
    private const BUDGET_MS = 300.0;

    /**
     * Budget of the worst plain-text filter a person can send: eight one-letter words, each a group of its own
     */
    private const WORST_TEXT_BUDGET_MS = 1000.0;
    private const DAY_MS = 86_400_000;
    private const RUNS = 5;

    /**
     * @var ActivitySearch|null
     */
    private static ?ActivitySearch $engine = null;

    public static function setUpBeforeClass(): void
    {
        self::journal()->seed();
    }

    public static function tearDownAfterClass(): void
    {
        self::journal()->remove();
        self::$engine = null;
    }

    /**
     * @return array<string, array{0: int, 1: list<array{0: string, 1: string}>, 2?: float}>
     */
    public static function shapes(): array
    {
        return [
            'last 7 days, no filters' => [7, []],
            'last 30 days, one record by id' => [30, [['record', '#recent']]],
            'all time, one record by id' => [0, [['record', '#recent']]],
            'all time, one setting by its exact path' => [0, [['path', PerformanceJournal::RARE_PATH]]],
            'last 30 days, text in a value' => [30, [['value', 'cotton']]],
            'last 30 days, a word in nearly every activity' => [30, [['text', 'item']]],
            'last 30 days, a word in no activity' => [30, [['text', 'zaltz']]],
            'last 30 days, two common words' => [30, [['text', 'item cotton']]],
            'last 30 days, a filter of eight single letters' => [
                30,
                [['text', 'a b c d e f g h i j k l m']],
                self::WORST_TEXT_BUDGET_MS,
            ],
        ];
    }

    /**
     * @param int $days 0 for all time
     * @param list<array{0: string, 1: string}> $chips
     * @param float $budgetMs
     */
    #[DataProvider('shapes')]
    public function testAnswersWithinBudget(int $days, array $chips, float $budgetMs = self::BUDGET_MS): void
    {
        $fromMs = $days === 0 ? 0 : PerformanceJournal::NOW_MS - $days * self::DAY_MS;
        $chips = array_map(
            static fn (array $chip): array => $chip[1] === '#recent'
                ? [$chip[0], '#' . self::journal()->recentItem()]
                : $chip,
            $chips
        );
        $request = (new SearchFixture())->request($fromMs, PerformanceJournal::NOW_MS, $chips, true);
        $engine = self::engine();

        $engine->search($request);
        $timings = [];
        for ($run = 0; $run < self::RUNS; $run++) {
            $started = hrtime(true);
            $page = $engine->search($request);
            $timings[] = (hrtime(true) - $started) / 1e6;
        }
        sort($timings);
        $median = $timings[intdiv(self::RUNS, 2)];

        $this->report((string) $this->dataName(), $request, $median, $page);
        self::assertLessThan($budgetMs, $median, sprintf('Median %.1f ms', $median));
    }

    /**
     * @return array<string, array{int, list<array{0: string, 1: string}>, string, string}>
     */
    public static function gridShapes(): array
    {
        return [
            'grid, last 7 days, newest first' => [7, [], 'occurred_at_ms', 'DESC'],
            'grid, last 7 days, by actor' => [7, [], 'actor_name', 'ASC'],
            'grid, last 30 days, by action' => [30, [], 'action', 'DESC'],
            'grid, last 30 days, by duration' => [30, [], 'duration_ms', 'DESC'],
            'grid, last 30 days, one record by id, by actor' => [30, [['record', '#recent']], 'actor_name', 'ASC'],
            'grid, last 30 days, a common word, by actor' => [30, [['text', 'item']], 'actor_name', 'ASC'],
        ];
    }

    /**
     * @param int $days
     * @param list<array{0: string, 1: string}> $chips
     * @param string $field
     * @param string $direction
     */
    #[DataProvider('gridShapes')]
    public function testAGridPageAnswersWithinBudget(int $days, array $chips, string $field, string $direction): void
    {
        $search = [
            'from' => (string) (PerformanceJournal::NOW_MS - $days * self::DAY_MS),
            'to' => (string) PerformanceJournal::NOW_MS,
            'c' => array_map(
                static fn (array $chip): string => $chip[0] . ':'
                    . ($chip[1] === '#recent' ? '#' . self::journal()->recentItem() : $chip[1]),
                $chips
            ),
            'changes' => '1',
        ];
        $value = SearchFixture::service(ActivitySearchParams::class)->filterValue($search);

        $this->gridPage($value, $field, $direction);
        $timings = [];
        $page = [];
        for ($run = 0; $run < self::RUNS; $run++) {
            $started = hrtime(true);
            $page = $this->gridPage($value, $field, $direction);
            $timings[] = (hrtime(true) - $started) / 1e6;
        }
        sort($timings);
        $median = $timings[intdiv(self::RUNS, 2)];

        fwrite(STDERR, sprintf(
            "\n[grid performance] %s: median %.1f ms, %d rows, total %d%s\n",
            (string) $this->dataName(),
            $median,
            $page['rows'],
            min($page['count'], SearchCounter::BOUND),
            $page['count'] > SearchCounter::BOUND ? '+' : ''
        ));
        self::assertGreaterThan(0, $page['rows']);
        self::assertLessThan(self::BUDGET_MS, $median, sprintf('Median %.1f ms', $median));
    }

    /**
     * One grid page of 50 rows with the search applied as the grid applies it, and its count up to the bound
     *
     * @param string $value The grid's search filter value
     * @param string $field
     * @param string $direction
     * @return array{rows: int, count: int}
     */
    private function gridPage(string $value, string $field, string $direction): array
    {
        $objectManager = Bootstrap::getObjectManager();
        $collection = $objectManager->create(GridCollection::class, [
            'mainTable' => ActivityResource::TABLE,
            'resourceModel' => ActivityResource::class,
        ]);
        self::assertInstanceOf(GridCollection::class, $collection);
        $applier = $objectManager->create(ActivitySearchFilter::class, [
            'requestCondition' => $objectManager->create(RequestCondition::class, [
                'criteriaResolver' => $objectManager->create(
                    CriteriaResolver::class,
                    ['conditionPool' => self::conditionPool()]
                ),
                'queryBuilder' => self::queryBuilder(),
            ]),
        ]);
        self::assertInstanceOf(ActivitySearchFilter::class, $applier);
        $filter = SearchFixture::service(FilterBuilder::class)
            ->setField('entity_id')
            ->setConditionType(ActivitySearchFilter::CONDITION_TYPE)
            ->setValue($value)
            ->create();

        $applier->apply($collection, $filter);
        $collection->setOrder($field, $direction)->setPageSize(50)->setCurPage(1);

        return ['rows' => count($collection->getItems()), 'count' => $collection->countUpTo(SearchCounter::BOUND)];
    }

    /**
     * Write the shape's timing and the page query's plan to standard error
     *
     * @param string $shape
     * @param SearchRequestInterface $request
     * @param float $medianMs
     * @param \Hryvinskyi\ActivityLog\Api\Data\Search\SearchPageInterface $page
     * @return void
     */
    private function report(
        string $shape,
        SearchRequestInterface $request,
        float $medianMs,
        \Hryvinskyi\ActivityLog\Api\Data\Search\SearchPageInterface $page
    ): void {
        $objectManager = Bootstrap::getObjectManager();
        $criteria = $objectManager->create(CriteriaResolver::class, ['conditionPool' => self::conditionPool()]);
        self::assertInstanceOf(CriteriaResolver::class, $criteria);
        $select = self::queryBuilder()->matches($request, $criteria->resolve($request), TimeScope::InRange, true)
            ->order([SearchQueryBuilder::ALIAS . '.occurred_at_ms DESC', SearchQueryBuilder::ALIAS . '.entity_id DESC'])
            ->limit($request->getLimit() + 1);

        $connection = SearchFixture::service(ResourceConnection::class)->getConnection();
        $plan = [];
        foreach ($connection->fetchAll('EXPLAIN ' . $select->assemble()) as $row) {
            if (is_array($row)) {
                $plan[] = sprintf(
                    '  %s %s type=%s key=%s rows=%s extra=%s',
                    is_scalar($row['select_type'] ?? null) ? (string) $row['select_type'] : '',
                    is_scalar($row['table'] ?? null) ? (string) $row['table'] : '',
                    is_scalar($row['type'] ?? null) ? (string) $row['type'] : '',
                    is_scalar($row['key'] ?? null) ? (string) $row['key'] : 'NULL',
                    is_scalar($row['rows'] ?? null) ? (string) $row['rows'] : '',
                    is_scalar($row['Extra'] ?? null) ? (string) $row['Extra'] : ''
                );
            }
        }

        fwrite(STDERR, sprintf(
            "\n[search performance] %s: median %.1f ms, %d events, total %s%s, narrowed %s\n%s\n",
            $shape,
            $medianMs,
            count($page->getEvents()),
            $page->getTotal()?->getCount() ?? '-',
            $page->getTotal()?->isAtLeast() === true ? '+' : '',
            $page->isNarrowed() ? 'yes' : 'no',
            implode("\n", $plan)
        ));
    }

    /**
     * The engine with the change channel and the fixture conditions a recording module would register
     *
     * @return ActivitySearch
     */
    private static function engine(): ActivitySearch
    {
        if (self::$engine === null) {
            $objectManager = Bootstrap::getObjectManager();
            $queryBuilder = self::queryBuilder();
            $engine = $objectManager->create(ActivitySearch::class, [
                'criteriaResolver' => $objectManager->create(
                    CriteriaResolver::class,
                    ['conditionPool' => self::conditionPool()]
                ),
                'queryBuilder' => $queryBuilder,
                'counter' => $objectManager->create(SearchCounter::class, ['queryBuilder' => $queryBuilder]),
            ]);
            self::assertInstanceOf(ActivitySearch::class, $engine);
            self::$engine = $engine;
        }

        return self::$engine;
    }

    /**
     * The query builder with "changes only" keeping the change channel
     *
     * @return SearchQueryBuilder
     */
    private static function queryBuilder(): SearchQueryBuilder
    {
        $objectManager = Bootstrap::getObjectManager();
        $builder = $objectManager->create(SearchQueryBuilder::class, [
            'changeChannels' => $objectManager->create(
                ChangeChannels::class,
                ['changeChannels' => ['change' => PerformanceJournal::CHANGE_CHANNEL]]
            ),
        ]);
        self::assertInstanceOf(SearchQueryBuilder::class, $builder);

        return $builder;
    }

    /**
     * The journal's conditions and the fixture ones
     *
     * @return ConditionPool
     */
    private static function conditionPool(): ConditionPool
    {
        $objectManager = Bootstrap::getObjectManager();
        $pool = $objectManager->create(ConditionPool::class, [
            'conditions' => [
                'record' => $objectManager->create(RecordCondition::class),
                'path' => $objectManager->create(FieldPathCondition::class),
                'value' => $objectManager->create(FieldTextCondition::class),
                'text' => $objectManager->create(
                    FieldTextCondition::class,
                    ['key' => 'text', 'withFieldNames' => true]
                ),
            ],
        ]);
        self::assertInstanceOf(ConditionPool::class, $pool);

        return $pool;
    }

    /**
     * @return PerformanceJournal
     */
    private static function journal(): PerformanceJournal
    {
        $journal = Bootstrap::getObjectManager()->get(PerformanceJournal::class);
        self::assertInstanceOf(PerformanceJournal::class, $journal);

        return $journal;
    }
}
