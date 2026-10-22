<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterface;
use Hryvinskyi\ActivityLog\Api\Search\SuggestionProviderInterface;
use Hryvinskyi\ActivityLog\Model\ResourceModel\RecentJournalValues;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\Journal\ActorSuggestions;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\Journal\ChannelSuggestions;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\Journal\OutcomeSuggestions;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\Journal\StoreViewSuggestions;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\Journal\ViaClientSuggestions;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\ProviderReader;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\RecentWindow;
use Hryvinskyi\ActivityLog\Model\Search\SuggestionPool;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\FixedClock;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The journal's own keys suggest what recent activities hold, over the real journal table
 *
 * Every test empties the journal inside its own transaction and stops the clock at NOW_MS.
 */
#[DbIsolation(true)]
class JournalSuggestionsTest extends TestCase
{
    private const NOW_MS = 1_792_500_000_000;
    private const DAY_MS = 86_400_000;

    /**
     * @var SearchFixture
     */
    private SearchFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new SearchFixture();
        $this->fixture->clear();
    }

    public function testActorsOfTheLast30DaysComeMostActiveFirstThenByNameWithTheirKindAndCount(): void
    {
        $this->fixture->insertMany([
            [self::NOW_MS - 1000, ['actor_name' => 'Jane Doe', 'actor_type' => 'admin']],
            [self::NOW_MS - 2000, ['actor_name' => 'Jane Doe', 'actor_type' => 'admin']],
            [self::NOW_MS - 3000, ['actor_name' => 'ERP Sync', 'actor_type' => 'integration']],
            [self::NOW_MS - 29 * self::DAY_MS, ['actor_name' => 'bin/magento', 'actor_type' => 'cli']],
            [self::NOW_MS - 31 * self::DAY_MS, ['actor_name' => 'Old Timer', 'actor_type' => 'admin']],
            [self::NOW_MS - 31 * self::DAY_MS, ['actor_name' => 'Old Timer', 'actor_type' => 'admin']],
            [self::NOW_MS - 31 * self::DAY_MS, ['actor_name' => 'Old Timer', 'actor_type' => 'admin']],
        ]);

        self::assertSame(
            [
                ['"Jane Doe"', 'Jane Doe', 'Admin', 2],
                ['"bin/magento"', 'bin/magento', 'CLI', 1],
                ['"ERP Sync"', 'ERP Sync', 'Integration', 1],
            ],
            $this->rows($this->provider(ActorSuggestions::class)),
            'Each actor is offered by their exact name, shown without the quotes'
        );
    }

    public function testClientsActorsWorkedThroughAreCountedAndActivitiesWithoutOneAreLeftOut(): void
    {
        $this->fixture->insertMany([
            [self::NOW_MS - 1000, ['via_client' => 'Desk Client 1.2']],
            [self::NOW_MS - 2000, ['via_client' => 'Desk Client 1.2']],
            [self::NOW_MS - 3000, ['via_client' => 'Pocket Client']],
            [self::NOW_MS - 4000, ['via_client' => null]],
            [self::NOW_MS - 5000, ['via_client' => '  ']],
        ]);

        self::assertSame(
            [['Desk Client 1.2', 'Desk Client 1.2', 'Client', 2], ['Pocket Client', 'Pocket Client', 'Client', 1]],
            $this->rows($this->provider(ViaClientSuggestions::class))
        );
    }

    public function testStoreViewsAreSuggestedByNameAndTheAdminAreaIsNotAStoreView(): void
    {
        $store = SearchFixture::service(StoreManagerInterface::class)->getStore('default');
        $this->fixture->insertMany([
            [self::NOW_MS - 1000, ['store_id' => (int) $store->getId()]],
            [self::NOW_MS - 2000, ['store_id' => (int) $store->getId()]],
            [self::NOW_MS - 3000, ['store_id' => 0]],
            [self::NOW_MS - 4000, ['store_id' => null]],
        ]);

        self::assertSame(
            [[(string) $store->getName(), (string) $store->getName(), 'Store view', 2]],
            $this->rows($this->provider(StoreViewSuggestions::class))
        );
    }

    public function testEveryChannelAndOutcomeIsOfferedWithoutACount(): void
    {
        $channels = $this->provider(ChannelSuggestions::class);
        $outcomes = $this->provider(OutcomeSuggestions::class);

        self::assertSame(['channel', 'outcome'], [$channels->getKey(), $outcomes->getKey()]);
        self::assertNotSame([], $channels->suggestions());
        foreach ($channels->suggestions() as $channel) {
            self::assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $channel->getValue());
            self::assertNull($channel->getCount());
        }
        self::assertSame(
            [['success', 'Success', '', null], ['failure', 'Failure', '', null], ['denied', 'Denied', '', null]],
            $this->rows($outcomes)
        );
    }

    public function testAQuietJournalSuggestsNoActorClientOrStoreView(): void
    {
        $this->fixture->insert(self::NOW_MS - 40 * self::DAY_MS, ['via_client' => 'Desk Client']);

        foreach ([ActorSuggestions::class, ViaClientSuggestions::class, StoreViewSuggestions::class] as $class) {
            self::assertSame([], $this->provider($class)->suggestions(), $class);
        }
    }

    public function testTheContainerRegistersTheJournalProvidersInThePool(): void
    {
        $pool = Bootstrap::getObjectManager()->create(SuggestionPool::class, [
            'reader' => Bootstrap::getObjectManager()->get(ProviderReader::class),
        ]);
        self::assertInstanceOf(SuggestionPool::class, $pool);
        $lists = $pool->lists();

        foreach (['channel', 'outcome'] as $key) {
            self::assertNotEmpty($lists[$key] ?? [], $key);
        }
    }

    /**
     * A journal provider over a window that ends at NOW_MS
     *
     * @template T of SuggestionProviderInterface
     * @param class-string<T> $class
     * @return T
     */
    private function provider(string $class): SuggestionProviderInterface
    {
        $objectManager = Bootstrap::getObjectManager();
        $window = $objectManager->create(RecentWindow::class, ['clock' => new FixedClock(self::NOW_MS)]);
        $provider = $objectManager->create($class, [
            'recentValues' => $objectManager->create(RecentJournalValues::class, ['window' => $window]),
        ]);
        self::assertInstanceOf($class, $provider);

        return $provider;
    }

    /**
     * @param SuggestionProviderInterface $provider
     * @return list<array{string, string, string, int|null}>
     */
    private function rows(SuggestionProviderInterface $provider): array
    {
        return array_map(
            static fn (SuggestionInterface $s): array => [
                $s->getValue(),
                $s->getLabel(),
                $s->getHint(),
                $s->getCount(),
            ],
            $provider->suggestions()
        );
    }
}
