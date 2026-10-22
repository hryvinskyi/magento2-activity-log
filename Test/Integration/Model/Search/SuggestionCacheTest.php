<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search;

use Hryvinskyi\ActivityLog\Model\Cache\Type\SearchCache;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\CachedSuggestionReader;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\SuggestionReaderInterface;
use Hryvinskyi\ActivityLog\Model\Search\SuggestionPool;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\CountingSuggestions;
use Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture\SearchFixture;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Suggestion lists are read through the "Activity Log Search" cache type the container wires: once per provider
 * and interface locale while it is enabled, every time while it is disabled
 */
#[AppArea('adminhtml')]
class SuggestionCacheTest extends TestCase
{
    /**
     * @var bool
     */
    private bool $wasEnabled = false;

    protected function setUp(): void
    {
        $state = SearchFixture::service(StateInterface::class);
        $this->wasEnabled = $state->isEnabled(SearchCache::TYPE_IDENTIFIER);
        $state->setEnabled(SearchCache::TYPE_IDENTIFIER, true);
        SearchFixture::service(SearchCache::class)->clean();
    }

    protected function tearDown(): void
    {
        SearchFixture::service(SearchCache::class)->clean();
        SearchFixture::service(StateInterface::class)
            ->setEnabled(SearchCache::TYPE_IDENTIFIER, $this->wasEnabled);
    }

    public function testTheContainerReadsProvidersThroughTheCache(): void
    {
        self::assertInstanceOf(
            CachedSuggestionReader::class,
            Bootstrap::getObjectManager()->get(SuggestionReaderInterface::class)
        );
    }

    public function testTheSecondCallIsServedFromTheCacheAndEachLocaleHasItsOwnList(): void
    {
        $provider = $this->provider();
        $english = $this->pool($provider, 'en_US');
        $danish = $this->pool($provider, 'da_DK');

        $first = $english->lists();
        $second = $english->lists();
        $danishList = $danish->lists();
        $danish->lists();

        self::assertSame(2, $provider->calls);
        self::assertSame(1, $first['tagged'][0]->getCount());
        self::assertSame(1, $second['tagged'][0]->getCount(), 'Served as first read');
        self::assertSame(2, $danishList['tagged'][0]->getCount(), 'Read for its own locale');
    }

    public function testWhileTheCacheTypeIsDisabledEveryCallAsksTheProvider(): void
    {
        SearchFixture::service(StateInterface::class)->setEnabled(SearchCache::TYPE_IDENTIFIER, false);
        $provider = $this->provider();
        $pool = $this->pool($provider, 'en_US');

        $pool->lists();
        $pool->lists();

        self::assertSame(2, $provider->calls);
    }

    /**
     * @return CountingSuggestions
     */
    private function provider(): CountingSuggestions
    {
        $provider = Bootstrap::getObjectManager()->create(CountingSuggestions::class);
        self::assertInstanceOf(CountingSuggestions::class, $provider);

        return $provider;
    }

    /**
     * A pool of the one provider, read through the container's cached reader in the given interface locale
     *
     * @param CountingSuggestions $provider
     * @param string $locale
     * @return SuggestionPool
     */
    private function pool(CountingSuggestions $provider, string $locale): SuggestionPool
    {
        $localeResolver = $this->createStub(ResolverInterface::class);
        $localeResolver->method('getLocale')->willReturn($locale);
        $objectManager = Bootstrap::getObjectManager();
        $pool = $objectManager->create(SuggestionPool::class, [
            'reader' => $objectManager->create(CachedSuggestionReader::class, ['localeResolver' => $localeResolver]),
            'suggestionProviders' => ['counting' => $provider],
        ]);
        self::assertInstanceOf(SuggestionPool::class, $pool);

        return $pool;
    }
}
