<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Search\Fixture;

use Hryvinskyi\ActivityLog\Api\Search\EventDetailProviderInterface;

/**
 * A detail provider for tests that fails on every call, as a broken module would
 */
class FailingDetailProvider implements EventDetailProviderInterface
{
    /**
     * @param string $channel
     */
    public function __construct(
        private readonly string $channel = 'zz_broken'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function supports(string $channel): bool
    {
        return $channel === $this->channel;
    }

    /**
     * @inheritDoc
     */
    public function detailsFor(array $activityIds, array $subjectLabels = []): array
    {
        throw new \RuntimeException('The details store is unreachable.');
    }
}
