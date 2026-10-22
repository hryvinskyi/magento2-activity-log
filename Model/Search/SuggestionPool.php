<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterface;
use Hryvinskyi\ActivityLog\Api\Search\SuggestionProviderInterface;
use Hryvinskyi\ActivityLog\Model\Search\Suggestion\SuggestionReaderInterface;
use Psr\Log\LoggerInterface;

/**
 * The values the search bar suggests, key by key
 *
 * A module adds its SuggestionProviderInterface to the "suggestionProviders" argument of this type, under a name unique
 * among providers. Providers of one key are joined in argument order: a value an earlier provider already offered
 * (compared without regard to case) is left out, and a key's list stops at MAX_SUGGESTIONS. A provider that fails is
 * logged and left out; the other lists are still offered.
 *
 * @api
 */
class SuggestionPool
{
    /**
     * @var array<string, SuggestionProviderInterface>
     */
    private array $providers = [];

    /**
     * @param SuggestionReaderInterface $reader
     * @param LoggerInterface $logger
     * @param array<string,SuggestionProviderInterface> $suggestionProviders
     */
    public function __construct(
        private readonly SuggestionReaderInterface $reader,
        private readonly LoggerInterface $logger,
        array $suggestionProviders = []
    ) {
        foreach ($suggestionProviders as $name => $provider) {
            $this->add((string) $name, $provider);
        }
    }

    /**
     * Every key's suggestions, keyed by search key; a key whose providers offer nothing is left out
     *
     * @return array<string, list<SuggestionInterface>>
     */
    public function lists(): array
    {
        $lists = [];
        $seen = [];
        foreach ($this->providers as $name => $provider) {
            $key = $provider->getKey();
            foreach ($this->suggestionsOf($name, $provider) as $suggestion) {
                $value = mb_strtolower($suggestion->getValue());
                if ($value === '' || isset($seen[$key][$value])
                    || count($lists[$key] ?? []) >= SuggestionProviderInterface::MAX_SUGGESTIONS
                ) {
                    continue;
                }
                $seen[$key][$value] = true;
                $lists[$key][] = $suggestion;
            }
        }

        return $lists;
    }

    /**
     * One provider's list, or none when it fails
     *
     * @param string $name
     * @param SuggestionProviderInterface $provider
     * @return list<SuggestionInterface>
     */
    private function suggestionsOf(string $name, SuggestionProviderInterface $provider): array
    {
        try {
            return $this->reader->read($name, $provider);
        } catch (\Throwable $failure) {
            $this->logger->error(
                'Search suggestions could not be read; the search bar offers the other keys\' values only.',
                ['provider' => $name, 'key' => $provider->getKey(), 'exception' => $failure]
            );

            return [];
        }
    }

    /**
     * Register a provider; anything else in the argument fails here, at construction
     *
     * @param string $name
     * @param SuggestionProviderInterface $provider
     * @return void
     */
    private function add(string $name, SuggestionProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }
}
