<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Search\Suggestion;

use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterfaceFactory;
use Hryvinskyi\ActivityLog\Model\Data\Search\Suggestion;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\CachedSuggestionReader;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\ProviderReader;
use Hryvinskyi\ActivityLog\Test\Unit\Model\Search\Fake\ListedSuggestions;
use Hryvinskyi\ActivityLog\Test\Unit\Model\Search\Fake\MemoryCache;
use Magento\Framework\Locale\ResolverInterface;
use PHPUnit\Framework\TestCase;

/**
 * A provider's list is kept per provider and interface locale for five minutes, and read back exactly as it was given
 */
class CachedSuggestionReaderTest extends TestCase
{
    /**
     * @var MemoryCache
     */
    private MemoryCache $cache;

    protected function setUp(): void
    {
        $this->cache = new MemoryCache();
    }

    public function testTheSecondReadIsServedFromTheCacheAsTheProviderGaveIt(): void
    {
        $provider = new ListedSuggestions('by', [['Jane Doe', 'Jane D.', 'admin', 3], ['cli', '', '', null]]);
        $reader = $this->reader('en_US');

        $first = $reader->read('actors', $provider);
        $second = $reader->read('actors', $provider);

        self::assertSame(1, $provider->calls);
        self::assertSame($this->rows($first), $this->rows($second));
        self::assertSame([['Jane Doe', 'Jane D.', 'admin', 3], ['cli', 'cli', '', null]], $this->rows($second));
        self::assertCount(1, $this->cache->entries);
        self::assertSame(CachedSuggestionReader::LIFETIME, array_values($this->cache->entries)[0]['lifetime']);
        self::assertSame(300, CachedSuggestionReader::LIFETIME);
    }

    public function testEachInterfaceLocaleHasItsOwnEntry(): void
    {
        $provider = new ListedSuggestions('outcome', [['success', 'Success']]);

        $this->reader('en_US')->read('outcomes', $provider);
        $this->reader('da_DK')->read('outcomes', $provider);
        $this->reader('da_DK')->read('outcomes', $provider);

        self::assertSame(2, $provider->calls);
        self::assertCount(2, $this->cache->entries);
    }

    public function testEachProviderHasItsOwnEntry(): void
    {
        $actors = new ListedSuggestions('by', [['Jane Doe']]);
        $moreActors = new ListedSuggestions('by', [['John Smith']]);
        $reader = $this->reader('en_US');

        $reader->read('actors', $actors);

        $moreRead = $reader->read('more_actors', $moreActors);

        self::assertSame([['John Smith', 'John Smith', '', null]], $this->rows($moreRead));
        self::assertSame(1, $moreActors->calls);
    }

    public function testAnEmptyListIsKeptLikeAnyOther(): void
    {
        $provider = new ListedSuggestions('via', []);
        $reader = $this->reader('en_US');

        $reader->read('vias', $provider);

        self::assertCount(0, $reader->read('vias', $provider));
        self::assertSame(1, $provider->calls);
    }

    public function testAnEntryThatIsNotAListIsReadFromTheProviderAgain(): void
    {
        $provider = new ListedSuggestions('by', [['Jane Doe']]);
        $reader = $this->reader('en_US');
        $reader->read('actors', $provider);
        $key = (string) array_key_first($this->cache->entries);

        foreach (['{broken', '{"a":1}', '[["",""," ",null]]', '[["x","x","",-4]]', '[[1,2,3,4]]'] as $entry) {
            $this->cache->entries[$key]['data'] = $entry;

            self::assertSame([['Jane Doe', 'Jane Doe', '', null]], $this->rows($reader->read('actors', $provider)));
        }
        self::assertSame(6, $provider->calls);
    }

    public function testAFailingProviderKeepsNothing(): void
    {
        $reader = $this->reader('en_US');

        try {
            $reader->read('broken', new ListedSuggestions('by', [], true));
            self::fail('The failure reaches the caller');
        } catch (\RuntimeException) {
            self::assertSame([], $this->cache->entries);
        }
    }

    /**
     * @param string $locale
     * @return CachedSuggestionReader
     */
    private function reader(string $locale): CachedSuggestionReader
    {
        $localeResolver = $this->createStub(ResolverInterface::class);
        $localeResolver->method('getLocale')->willReturn($locale);
        $factory = $this->createStub(SuggestionInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(static fn (): Suggestion => new Suggestion());

        return new CachedSuggestionReader(new ProviderReader(), $this->cache, $localeResolver, $factory);
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
