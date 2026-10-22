<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterface;
use Hryvinskyi\ActivityLog\Api\Search\SuggestionProviderInterface;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\ProviderReader;
use Hryvinskyi\ActivityLog\Model\Search\SuggestionPool;
use Hryvinskyi\ActivityLog\Test\Unit\Model\Search\Fake\ListedSuggestions;
use Hryvinskyi\ActivityLog\Test\Unit\Model\Search\Fake\RecordingLogger;
use PHPUnit\Framework\TestCase;

/**
 * The pool joins the providers of each key, offers a value once, bounds each list and survives a failing provider
 */
class SuggestionPoolTest extends TestCase
{
    public function testListsAreKeyedBySearchKeyInProviderOrder(): void
    {
        $pool = $this->pool([
            'actors' => new ListedSuggestions('by', [['Jane Doe', '', 'admin', 3], ['ERP Sync', '', 'integration', 1]]),
            'outcomes' => new ListedSuggestions('outcome', [['success', 'Success']]),
        ]);

        $lists = $pool->lists();

        self::assertSame(['by', 'outcome'], array_keys($lists));
        self::assertSame(
            [['Jane Doe', 'Jane Doe', 'admin', 3], ['ERP Sync', 'ERP Sync', 'integration', 1]],
            $this->rows($lists['by'])
        );
        self::assertSame([['success', 'Success', '', null]], $this->rows($lists['outcome']));
    }

    public function testProvidersOfOneKeyAreJoinedAndAValueIsOfferedOnceAsTheFirstProviderGaveIt(): void
    {
        $pool = $this->pool([
            'store_views' => new ListedSuggestions('store', [['Default Store View', '', 'Store view', 40]]),
            'scopes' => new ListedSuggestions('store', [
                ['default store view', '', 'Store view', 7],
                ['Main Website', '', 'Website', 5],
            ]),
        ]);

        self::assertSame(
            [
                ['Default Store View', 'Default Store View', 'Store view', 40],
                ['Main Website', 'Main Website', 'Website', 5],
            ],
            $this->rows($pool->lists()['store'])
        );
    }

    public function testAKeysListStopsAtTheLimitWhateverItsProvidersOffer(): void
    {
        $many = static fn (string $prefix): array => array_map(
            static fn (int $i): array => [$prefix . $i],
            range(1, 150)
        );
        $pool = $this->pool([
            'first' => new ListedSuggestions('field', $many('a')),
            'second' => new ListedSuggestions('field', $many('b')),
        ]);

        $list = $pool->lists()['field'];

        self::assertCount(SuggestionProviderInterface::MAX_SUGGESTIONS, $list);
        self::assertSame('b50', $list[SuggestionProviderInterface::MAX_SUGGESTIONS - 1]->getValue());
    }

    public function testAFailingProviderIsLoggedAndTheOtherListsAreStillOffered(): void
    {
        $logger = new RecordingLogger();
        $pool = new SuggestionPool(new ProviderReader(), $logger, [
            'broken' => new ListedSuggestions('by', [['Jane Doe']], true),
            'outcomes' => new ListedSuggestions('outcome', [['success']]),
        ]);

        $lists = $pool->lists();

        self::assertSame(['outcome'], array_keys($lists));
        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertSame(['broken', 'by'], [
            $logger->records[0]['context']['provider'] ?? null,
            $logger->records[0]['context']['key'] ?? null,
        ]);
    }

    public function testAKeyWhoseProvidersOfferNothingIsLeftOut(): void
    {
        self::assertSame([], $this->pool(['quiet' => new ListedSuggestions('via', [])])->lists());
    }

    /**
     * @param array<string, SuggestionProviderInterface> $providers
     * @return SuggestionPool
     */
    private function pool(array $providers): SuggestionPool
    {
        return new SuggestionPool(new ProviderReader(), new RecordingLogger(), $providers);
    }

    /**
     * @param list<SuggestionInterface> $suggestions
     * @return list<array{string, string, string, int|null}>
     */
    private function rows(array $suggestions): array
    {
        return array_map(
            static fn (SuggestionInterface $s): array => [
                $s->getValue(),
                $s->getLabel(),
                $s->getHint(),
                $s->getCount(),
            ],
            $suggestions
        );
    }
}
