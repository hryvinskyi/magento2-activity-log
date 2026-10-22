<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Suggestion;

use Hryvinskyi\ActivityLog\Api\Data\Search\SuggestionInterface;
use Hryvinskyi\ActivityLog\Api\Search\SuggestionProviderInterface;

/**
 * How the suggestion pool obtains one provider's list; the place a cache sits in front of the providers
 */
interface SuggestionReaderInterface
{
    /**
     * The provider's suggestions
     *
     * @param string $name The provider's name in the pool, unique among providers
     * @param SuggestionProviderInterface $provider
     * @return list<SuggestionInterface>
     */
    public function read(string $name, SuggestionProviderInterface $provider): array;
}
