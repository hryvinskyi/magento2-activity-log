<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search\Suggestion;

use Hryvinskyi\ActivityLog\Api\Search\SuggestionProviderInterface;

/**
 * Asks the provider itself, every time
 */
class ProviderReader implements SuggestionReaderInterface
{
    /**
     * @inheritDoc
     */
    public function read(string $name, SuggestionProviderInterface $provider): array
    {
        return $provider->suggestions();
    }
}
