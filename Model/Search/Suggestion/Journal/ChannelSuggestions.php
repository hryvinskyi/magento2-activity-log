<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Suggestion\Journal;

use Hryvinskyi\ActivityLog\Api\ChannelPoolInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Search\SuggestionProviderInterface;

/**
 * "channel": every registered channel, by code, with its label; read from the channel pool, not the journal
 */
class ChannelSuggestions implements SuggestionProviderInterface
{
    /**
     * @param ChannelPoolInterface $channelPool
     * @param SuggestionInterfaceFactory $suggestionFactory
     */
    public function __construct(
        private readonly ChannelPoolInterface $channelPool,
        private readonly SuggestionInterfaceFactory $suggestionFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return 'channel';
    }

    /**
     * @inheritDoc
     */
    public function suggestions(): array
    {
        $suggestions = [];
        foreach ($this->channelPool->all() as $channel) {
            $suggestions[] = $this->suggestionFactory->create()
                ->withValue($channel->getCode(), (string) __($channel->getLabel()));
        }

        return $suggestions;
    }
}
