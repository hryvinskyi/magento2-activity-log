<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Detail;

use Hryvinskyi\ActivityLog\Api\Search\EventDetailProviderInterface;

/**
 * The modules that describe the activities of their channels
 *
 * A module adds its EventDetailProviderInterface to the "detailProviders" argument of this type. The first provider,
 * in argument order, that supports a channel serves it; a channel nobody supports is shown from the journal alone.
 *
 * @api
 */
class DetailProviderPool
{
    /**
     * @var list<EventDetailProviderInterface>
     */
    private array $providers = [];

    /**
     * @var array<string, EventDetailProviderInterface|null>
     */
    private array $byChannel = [];

    /**
     * @param array<string,EventDetailProviderInterface> $detailProviders
     */
    public function __construct(array $detailProviders = [])
    {
        foreach ($detailProviders as $provider) {
            $this->add($provider);
        }
    }

    /**
     * The provider serving a channel, or null when none does
     *
     * @param string $channel
     * @return EventDetailProviderInterface|null
     */
    public function providerFor(string $channel): ?EventDetailProviderInterface
    {
        if (!array_key_exists($channel, $this->byChannel)) {
            $this->byChannel[$channel] = $this->firstSupporting($channel);
        }

        return $this->byChannel[$channel];
    }

    /**
     * The first provider that supports the channel
     *
     * @param string $channel
     * @return EventDetailProviderInterface|null
     */
    private function firstSupporting(string $channel): ?EventDetailProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($channel)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Register a provider; anything else in the argument fails here, at construction
     *
     * @param EventDetailProviderInterface $provider
     * @return void
     */
    private function add(EventDetailProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }
}
