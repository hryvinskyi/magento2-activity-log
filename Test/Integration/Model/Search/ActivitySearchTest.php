<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search;

use Hryvinskyi\ActivityLog\Api\ChannelPoolInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCursorInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchPageInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterface;
use Hryvinskyi\ActivityLog\Api\Search\ActivitySearchInterface;
use Hryvinskyi\ActivityLog\Model\Search\ActivitySearch;
use Hryvinskyi\ActivityLog\Model\Search\ChangeChannels;
use Hryvinskyi\ActivityLog\Model\Search\ConditionPool;
use Hryvinskyi\ActivityLog\Model\Search\CriteriaResolver;
use Hryvinskyi\ActivityLog\Model\Search\Query\SearchQueryBuilder;
use Hryvinskyi\ActivityLog\Model\Search\SearchCounter;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\ActionCondition;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The journal search over the real table: range, journal keys, keyset paging, the candidate limit and the counts
 *
 * Every test empties the journal first, inside its own transaction.
 */
#[DbIsolation(true)]
class ActivitySearchTest extends TestCase
{
    private const NOW_MS = 1_792_500_000_000;
    private const MINUTE_MS = 60_000;

    /**
     * @var SearchFixture
     */
    private SearchFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new SearchFixture();
        $this->fixture->clear();
    }

    public function testTheRangeIncludesBothEndsAndNothingOutsideIt(): void
    {
        $from = self::NOW_MS - 10 * self::MINUTE_MS;
        $this->fixture->insert($from - 1);
        $first = $this->fixture->insert($from);
        $middle = $this->fixture->insert($from + 5 * self::MINUTE_MS);
        $last = $this->fixture->insert(self::NOW_MS);
        $this->fixture->insert(self::NOW_MS + 1);

        $page = $this->search($this->fixture->request($from, self::NOW_MS));

        self::assertSame([$last, $middle, $first], $this->fixture->ids($page));
        self::assertSame([3, false], [$page->getTotal()?->getCount(), $page->getTotal()?->isAtLeast()]);
        self::assertNull($page->getOutside(), 'A page with results and no filters needs no count outside the range');
        self::assertNull($page->getOverall());
        self::assertNull($page->getNextCursor());
        self::assertFalse($page->isNarrowed());
        self::assertFalse($page->isJournalEmpty());
    }

    public function testEachJournalKeyFindsWhatItNames(): void
    {
        $jane = $this->fixture->insert(self::NOW_MS - 3000, [
            'actor_type' => 'admin',
            'actor_name' => 'Jane Doe',
            'via_client' => 'Desk Assistant',
            'channel' => 'zz_one',
            'outcome' => 'success',
            'store_id' => 1,
            'correlation_id' => 'op-search-0001',
        ]);
        $erp = $this->fixture->insert(self::NOW_MS - 2000, [
            'actor_type' => 'integration',
            'actor_name' => 'ERP 100% Sync',
            'channel' => 'zz_two',
            'outcome' => 'failure',
            'store_id' => 0,
            'correlation_id' => 'op-search-0002',
        ]);
        $cli = $this->fixture->insert(self::NOW_MS - 1000, [
            'actor_type' => 'cli',
            'actor_name' => 'bin/magento',
            'channel' => 'zz_two',
            'outcome' => 'denied',
            'correlation_id' => 'op-search-0002',
        ]);

        $expectations = [
            'by a kind of actor' => [['by', 'admin'], [$jane]],
            'by a kind of actor, by its label' => [['by', 'CLI'], [$cli]],
            'by part of a name' => [['by', 'jane'], [$jane]],
            'by a typed percent sign, which is not a wildcard' => [['by', '100%'], [$erp]],
            'by a lone percent sign' => [['by', '%'], [$erp]],
            'by a typed underscore, which is not a wildcard' => [['by', 'j_ne'], []],
            'via part of a client' => [['via', 'desk'], [$jane]],
            'channel by code' => [['channel', 'zz_two'], [$cli, $erp]],
            'outcome by code' => [['outcome', 'failure'], [$erp]],
            'outcome by label' => [['outcome', 'Denied'], [$cli]],
            'outcome that does not exist' => [['outcome', 'maybe'], []],
            'store by code' => [['store', 'default'], [$jane]],
            'store by part of its name' => [['store', 'default store'], [$jane]],
            'store by the admin code' => [['store', 'admin'], [$erp]],
            'store that does not exist' => [['store', 'atlantis'], []],
            'request by correlation id' => [['request', 'op-search-0002'], [$cli, $erp]],
            'request that is not a correlation id' => [['request', "x' OR '1'='1"], []],
        ];

        foreach ($expectations as $case => [$chip, $expected]) {
            $page = $this->search($this->fixture->request(0, self::NOW_MS, [$chip]));
            self::assertSame($expected, $this->fixture->ids($page), $case);
        }

        $both = $this->search($this->fixture->request(0, self::NOW_MS, [['channel', 'zz_two'], ['outcome', 'denied']]));
        self::assertSame([$cli], $this->fixture->ids($both), 'Filters are all required');
    }

    public function testANameInDoubleQuotesIsThatActorExactlyAndNeverAKindOfActor(): void
    {
        $named = $this->fixture->insert(self::NOW_MS - 3000, ['actor_type' => 'admin', 'actor_name' => 'admin']);
        $other = $this->fixture->insert(self::NOW_MS - 2000, ['actor_type' => 'admin', 'actor_name' => 'Jane Doe']);
        $longer = $this->fixture->insert(
            self::NOW_MS - 1000,
            ['actor_type' => 'integration', 'actor_name' => 'admin sync']
        );

        $find = fn (string $value): array => $this->fixture->ids(
            $this->search($this->fixture->request(0, self::NOW_MS, [['by', $value]]))
        );

        self::assertSame([$other, $named], $find('admin'), 'Unquoted, the kind of actor');
        self::assertSame([$named], $find('"admin"'), 'Quoted, the user named so and no one else');
        self::assertSame([$named], $find('"ADMIN"'), 'Case aside');
        self::assertSame([$longer], $find('sync'), 'Unquoted, a part of a name');
        self::assertSame([], $find('"admin syn"'), 'Quoted, never a part');
    }

    public function testStoreViewsSeparatedByABarMatchAnyOfThem(): void
    {
        $admin = $this->fixture->insert(self::NOW_MS - 2000, ['store_id' => 0]);
        $view = $this->fixture->insert(self::NOW_MS - 1000, ['store_id' => 1]);

        $page = $this->search($this->fixture->request(0, self::NOW_MS, [['store', 'default | admin']]));

        self::assertSame([$view, $admin], $this->fixture->ids($page));
    }

    public function testAChannelIsFoundByItsLabel(): void
    {
        $channels = self::service(ChannelPoolInterface::class)->all();
        self::assertNotEmpty($channels, 'The installed modules register channels');
        $channel = $channels[0];
        $id = $this->fixture->insert(self::NOW_MS, ['channel' => $channel->getCode()]);
        $this->fixture->insert(self::NOW_MS, ['channel' => 'zz_other']);

        $page = $this->search($this->fixture->request(0, self::NOW_MS, [['channel', $channel->getLabel()]]));

        self::assertSame([$id], $this->fixture->ids($page));
    }

    public function testPagesFollowEachOtherWithoutGapsOrRepeatsAcrossEqualTimes(): void
    {
        $rows = [];
        for ($i = 0; $i < 7; $i++) {
            $rows[] = [self::NOW_MS - 1000, []];
        }
        $rows[] = [self::NOW_MS, []];
        $rows[] = [self::NOW_MS - 2000, []];
        $rows[] = [self::NOW_MS - 1000 + 1, []];
        $this->fixture->insertMany($rows);
        $expected = $this->fixture->idsWhere('1 = 1');

        $seen = [];
        $pages = 0;
        $cursor = null;
        do {
            $page = $this->search($this->fixture->request(0, self::NOW_MS, [], false, 3, $cursor));
            $seen = array_merge($seen, $this->fixture->ids($page));
            if ($pages > 0) {
                self::assertNull($page->getTotal(), 'Only the first page carries the counts');
            }
            $cursor = $page->getNextCursor();
            $pages++;
        } while ($cursor !== null && $pages < 10);

        self::assertSame($expected, $seen);
        self::assertSame(4, $pages);
    }

    public function testAValueSearchLooksOnlyAmongTheNewestTenThousandCandidates(): void
    {
        $rows = [];
        for ($i = 0; $i < 11_000; $i++) {
            $needle = $i < 3 || $i >= 10_998;
            $rows[] = [self::NOW_MS - $i * 10, ['channel' => 'zz_cap', 'via_client' => $needle ? 'Needle' : 'Hay']];
        }
        $this->fixture->insertMany($rows);
        $needles = $this->fixture->idsWhere("via_client = 'Needle'");
        self::assertCount(5, $needles);

        $page = $this->search($this->fixture->request(0, self::NOW_MS, [['via', 'needle']]));

        self::assertSame(array_slice($needles, 0, 3), $this->fixture->ids($page));
        self::assertTrue($page->isNarrowed());
        self::assertSame([3, true], [$page->getTotal()?->getCount(), $page->getTotal()?->isAtLeast()]);
    }

    public function testTheCandidateLimitFollowsThePagePosition(): void
    {
        $rows = [];
        for ($i = 0; $i < 11_000; $i++) {
            $needle = $i < 3 || $i >= 10_998;
            $rows[] = [self::NOW_MS - $i * 10, ['channel' => 'zz_cap', 'via_client' => $needle ? 'Needle' : 'Hay']];
        }
        $this->fixture->insertMany($rows);
        $all = $this->fixture->idsWhere("channel = 'zz_cap'");
        $needles = $this->fixture->idsWhere("via_client = 'Needle'");

        $first = $this->search($this->fixture->request(0, self::NOW_MS, [['via', 'needle']], false, 2));
        $second = $this->search(
            $this->fixture->request(0, self::NOW_MS, [['via', 'needle']], false, 2, $first->getNextCursor())
        );
        $deep = $this->search(
            $this->fixture->request(0, self::NOW_MS, [['via', 'needle']], false, 2, $this->cursorAt($all[1500]))
        );

        self::assertSame(array_slice($needles, 0, 2), $this->fixture->ids($first));
        self::assertSame([$needles[2]], $this->fixture->ids($second), 'The next 10,000 older candidates');
        self::assertTrue($second->isNarrowed());
        self::assertSame(array_slice($needles, 3, 2), $this->fixture->ids($deep), 'Fewer than 10,000 left');
        self::assertFalse($deep->isNarrowed());
    }

    public function testCountsStopAtTenThousandExceptForTheRangeAlone(): void
    {
        $rows = [];
        for ($i = 0; $i < 10_005; $i++) {
            $rows[] = [self::NOW_MS - $i, ['channel' => 'zz_many']];
        }
        $this->fixture->insertMany($rows);

        $filtered = $this->search($this->fixture->request(0, self::NOW_MS, [['channel', 'zz_many']], false, 1));
        $rangeOnly = $this->search($this->fixture->request(0, self::NOW_MS, [], false, 1));

        self::assertSame([10_000, true], [$filtered->getTotal()?->getCount(), $filtered->getTotal()?->isAtLeast()]);
        self::assertSame([10_005, false], [$rangeOnly->getTotal()?->getCount(), $rangeOnly->getTotal()?->isAtLeast()]);
        self::assertFalse($filtered->isNarrowed(), 'No value search, no candidate limit');
    }

    public function testFiltersAlsoCountMatchesOutsideTheRangeAndTheNewestOfThem(): void
    {
        $from = self::NOW_MS - 60 * self::MINUTE_MS;
        $this->fixture->insert($from - 5, ['channel' => 'zz_out']);
        $this->fixture->insert($from + 5, ['channel' => 'zz_out']);
        $this->fixture->insert(self::NOW_MS - 5, ['channel' => 'zz_out']);
        $this->fixture->insert(self::NOW_MS + 10, ['channel' => 'zz_out']);
        $this->fixture->insert(self::NOW_MS + 20, ['channel' => 'zz_out']);
        $this->fixture->insert(self::NOW_MS + 30, ['channel' => 'zz_else']);

        $page = $this->search($this->fixture->request($from, self::NOW_MS, [['channel', 'zz_out']]));

        self::assertSame([2, false], [$page->getTotal()?->getCount(), $page->getTotal()?->isAtLeast()]);
        self::assertSame([3, false], [$page->getOutside()?->getCount(), $page->getOutside()?->isAtLeast()]);
        self::assertSame(self::NOW_MS + 20, $page->getLatestOutsideMs());
        self::assertSame([5, false], [$page->getOverall()?->getCount(), $page->getOverall()?->isAtLeast()]);
    }

    public function testAnEmptyPageSaysWhatLiesOutsideTheRangeAndWhetherTheJournalIsEmpty(): void
    {
        $empty = $this->search($this->fixture->request(0, self::NOW_MS));
        self::assertSame([], $empty->getEvents());
        self::assertTrue($empty->isJournalEmpty());
        self::assertSame(0, $empty->getOutside()?->getCount());
        self::assertNull($empty->getLatestOutsideMs());

        $this->fixture->insert(self::NOW_MS - 100 * self::MINUTE_MS);
        $elsewhere = $this->search($this->fixture->request(self::NOW_MS - self::MINUTE_MS, self::NOW_MS));

        self::assertSame([], $elsewhere->getEvents());
        self::assertFalse($elsewhere->isJournalEmpty());
        self::assertSame(1, $elsewhere->getOutside()?->getCount());
        self::assertSame(self::NOW_MS - 100 * self::MINUTE_MS, $elsewhere->getLatestOutsideMs());
        self::assertNull($elsewhere->getOverall(), 'Without filters there is no overall count');
    }

    public function testChangesOnlyKeepsTheChannelsThatRecordChanges(): void
    {
        $change = $this->fixture->insert(self::NOW_MS - 2, ['channel' => 'zz_change']);
        $request = $this->fixture->insert(self::NOW_MS - 1, ['channel' => 'zz_request']);

        $withChanges = $this->engine(['zz_change' => 'zz_change'])
            ->search($this->fixture->request(0, self::NOW_MS, [], true));
        $withoutAny = $this->engine([])->search($this->fixture->request(0, self::NOW_MS, [], true));

        self::assertSame([$change], $this->fixture->ids($withChanges));
        self::assertSame(
            [$request, $change],
            $this->fixture->ids($withoutAny),
            'With no channel recording changes the switch keeps everything'
        );
    }

    public function testConditionsRegisteredForOneKeyAreAlternatives(): void
    {
        $alpha = $this->fixture->insert(self::NOW_MS - 4, ['action' => 'a-one']);
        $beta = $this->fixture->insert(self::NOW_MS - 3, ['action' => 'b-one']);
        $gamma = $this->fixture->insert(self::NOW_MS - 2, ['action' => 'c-one']);
        $defaultStore = $this->fixture->insert(self::NOW_MS - 1, ['store_id' => 1]);
        $scoped = $this->fixture->insert(self::NOW_MS, ['action' => 'scope-default']);

        $engine = $this->withConditions([
            'TestConditionTaggedA' => ['key' => 'tagged', 'prefix' => 'a-', 'residual' => false],
            'TestConditionTaggedB' => ['key' => 'tagged', 'prefix' => 'b-', 'residual' => false],
            'TestConditionStoreScope' => ['key' => 'store', 'prefix' => 'scope-', 'residual' => false],
            'TestConditionLooseA' => ['key' => 'loose', 'prefix' => 'a-', 'residual' => true],
            'TestConditionLooseC' => ['key' => 'loose', 'prefix' => 'c-', 'residual' => true],
        ]);

        $tagged = $engine->search($this->fixture->request(0, self::NOW_MS, [['tagged', 'one']]));
        $loose = $engine->search($this->fixture->request(0, self::NOW_MS, [['loose', 'one']]));
        $looseAndTagged = $engine->search(
            $this->fixture->request(0, self::NOW_MS, [['loose', 'one'], ['tagged', 'one']])
        );
        $store = $engine->search($this->fixture->request(0, self::NOW_MS, [['store', 'default']]));
        $nothing = $engine->search($this->fixture->request(0, self::NOW_MS, [['tagged', 'nothing']]));

        self::assertSame([$beta, $alpha], $this->fixture->ids($tagged));
        self::assertSame([$gamma, $alpha], $this->fixture->ids($loose), 'Residual alternatives among the candidates');
        self::assertSame([$alpha], $this->fixture->ids($looseAndTagged));
        self::assertSame(
            [$scoped, $defaultStore],
            $this->fixture->ids($store),
            'The journal and a module both serve it'
        );
        self::assertSame([], $this->fixture->ids($nothing), 'A key that matches nothing is not dropped');
    }

    /**
     * A page from the engine as the container wires it
     *
     * @param SearchRequestInterface $request
     * @return SearchPageInterface
     */
    private function search(SearchRequestInterface $request): SearchPageInterface
    {
        return self::service(ActivitySearchInterface::class)->search($request);
    }

    /**
     * An engine whose "changes only" keeps these channels
     *
     * @param array<string, string> $changeChannels
     * @return ActivitySearch
     */
    private function engine(array $changeChannels): ActivitySearch
    {
        $objectManager = Bootstrap::getObjectManager();
        $queryBuilder = $objectManager->create(SearchQueryBuilder::class, [
            'changeChannels' => $objectManager->create(ChangeChannels::class, ['changeChannels' => $changeChannels]),
        ]);
        $engine = $objectManager->create(ActivitySearch::class, [
            'queryBuilder' => $queryBuilder,
            'counter' => $objectManager->create(SearchCounter::class, ['queryBuilder' => $queryBuilder]),
        ]);
        self::assertInstanceOf(ActivitySearch::class, $engine);

        return $engine;
    }

    /**
     * An engine whose condition pool also holds these test conditions, added to the "conditions" argument the way a
     * module's di.xml adds them; the argument is restored when the test ends
     *
     * @param array<string, array{key: string, prefix: string, residual: bool}> $virtualTypes
     * @return ActivitySearch
     */
    private function withConditions(array $virtualTypes): ActivitySearch
    {
        $objectManager = Bootstrap::getObjectManager();
        $arguments = self::service(ConfigInterface::class)->getArguments(ConditionPool::class);
        $original = is_array($arguments) && is_array($arguments['conditions'] ?? null) ? $arguments['conditions'] : [];

        $configuration = [];
        $conditions = $original;
        foreach ($virtualTypes as $name => $condition) {
            $configuration[$name] = ['type' => ActionCondition::class, 'arguments' => $condition];
            $conditions[$name] = ['instance' => $name];
        }
        $configuration[ConditionPool::class] = ['arguments' => ['conditions' => $conditions]];
        $objectManager->configure($configuration);

        try {
            $resolver = $objectManager->create(CriteriaResolver::class, [
                'conditionPool' => $objectManager->create(ConditionPool::class),
            ]);
        } finally {
            $objectManager->configure([ConditionPool::class => ['arguments' => ['conditions' => $original]]]);
        }

        $engine = $objectManager->create(ActivitySearch::class, ['criteriaResolver' => $resolver]);
        self::assertInstanceOf(ActivitySearch::class, $engine);

        return $engine;
    }

    /**
     * A page position just after an activity
     *
     * @param int $activityId
     * @return \Hryvinskyi\ActivityLog\Api\Data\Search\SearchCursorInterface
     */
    private function cursorAt(int $activityId): \Hryvinskyi\ActivityLog\Api\Data\Search\SearchCursorInterface
    {
        $connection = self::service(\Magento\Framework\App\ResourceConnection::class)->getConnection();
        $ms = $connection->fetchOne(
            $connection->select()
                ->from($connection->getTableName('hryvinskyi_activity_log'), ['occurred_at_ms'])
                ->where('entity_id = ?', $activityId)
        );
        self::assertIsNumeric($ms);

        return self::service(SearchCursorInterfaceFactory::class)->create()->withPosition((int) $ms, $activityId);
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
        $service = Bootstrap::getObjectManager()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
