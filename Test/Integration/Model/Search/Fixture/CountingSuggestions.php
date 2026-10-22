<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture;

use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Search\SuggestionProviderInterface;

/**
 * A provider for tests that offers one value and counts how often it is asked
 */
class CountingSuggestions implements SuggestionProviderInterface
{
    /**
     * @var int
     */
    public int $calls = 0;

    /**
     * @param SuggestionInterfaceFactory $suggestionFactory
     * @param string $value
     */
    public function __construct(
        private readonly SuggestionInterfaceFactory $suggestionFactory,
        private readonly string $value = 'counted'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return 'tagged';
    }

    /**
     * @inheritDoc
     */
    public function suggestions(): array
    {
        $this->calls++;

        return [$this->suggestionFactory->create()->withValue($this->value)->withCount($this->calls)];
    }
}
