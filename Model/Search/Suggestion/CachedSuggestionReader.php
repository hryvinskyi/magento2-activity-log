<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Suggestion;

use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Search\SuggestionProviderInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Locale\ResolverInterface;

/**
 * Keeps each provider's list in the "Activity Log Search" cache type for LIFETIME seconds, in front of the providers
 *
 * - The key holds the provider's name and the admin's interface locale: labels and hints are translated, so a list
 *   read for one locale is never served to another. Nothing else in a list depends on who asks: every admin who may
 *   search may see all of it.
 * - Staleness up to LIFETIME is accepted: suggestions only help typing, and the search itself always reads the log.
 *   An empty list is kept like any other, so a quiet log is not asked again on every page load.
 * - While the cache type is disabled nothing is kept, and every read asks the provider.
 * - An entry is JSON; one that cannot be read back is asked for again. A provider that fails keeps nothing.
 */
class CachedSuggestionReader implements SuggestionReaderInterface
{
    /**
     * Seconds a list is kept
     */
    public const LIFETIME = 300;

    private const KEY_PREFIX = 'hryvinskyi_activity_suggest_';

    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE;

    /**
     * @param SuggestionReaderInterface $inner Reader that asks the providers
     * @param FrontendInterface $cache The "Activity Log Search" cache type
     * @param ResolverInterface $localeResolver
     * @param SuggestionInterfaceFactory $suggestionFactory
     */
    public function __construct(
        private readonly SuggestionReaderInterface $inner,
        private readonly FrontendInterface $cache,
        private readonly ResolverInterface $localeResolver,
        private readonly SuggestionInterfaceFactory $suggestionFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function read(string $name, SuggestionProviderInterface $provider): array
    {
        $key = $this->keyOf($name);
        $entry = $this->cache->load($key);
        $cached = is_string($entry) ? $this->decode($entry) : null;
        if ($cached !== null) {
            return $cached;
        }

        $suggestions = $this->inner->read($name, $provider);
        $this->cache->save($this->encode($suggestions), $key, [], self::LIFETIME);

        return $suggestions;
    }

    /**
     * Cache key of a provider's list in the current interface locale
     *
     * Cache ids allow letters, digits and "_" only, so the name and locale are hashed rather than written out.
     *
     * @param string $name
     * @return string
     */
    private function keyOf(string $name): string
    {
        return self::KEY_PREFIX . sha1($name . "\n" . $this->localeResolver->getLocale());
    }

    /**
     * A list as an entry
     *
     * @param list<SuggestionInterface> $suggestions
     * @return string
     */
    private function encode(array $suggestions): string
    {
        return json_encode(
            array_map(
                static fn (SuggestionInterface $suggestion): array => [
                    $suggestion->getValue(),
                    $suggestion->getLabel(),
                    $suggestion->getHint(),
                    $suggestion->getCount(),
                ],
                $suggestions
            ),
            self::JSON_FLAGS
        );
    }

    /**
     * The list an entry holds, or null when the entry is not one
     *
     * @param string $entry
     * @return list<SuggestionInterface>|null
     */
    private function decode(string $entry): ?array
    {
        try {
            $rows = json_decode($entry, true, 4, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($rows) || !array_is_list($rows)) {
            return null;
        }

        $suggestions = [];
        foreach ($rows as $row) {
            $suggestion = is_array($row) ? $this->restore($row) : null;
            if ($suggestion === null) {
                return null;
            }
            $suggestions[] = $suggestion;
        }

        return $suggestions;
    }

    /**
     * A suggestion from one row of an entry, or null when the row is not one
     *
     * @param array<mixed> $row
     * @return SuggestionInterface|null
     */
    private function restore(array $row): ?SuggestionInterface
    {
        [$value, $label, $hint, $count] = array_pad(array_values($row), 4, false);
        if (!is_string($value) || !is_string($label) || !is_string($hint) || !($count === null || is_int($count))) {
            return null;
        }

        try {
            return $this->suggestionFactory->create()->withValue($value, $label)->withHint($hint)->withCount($count);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
