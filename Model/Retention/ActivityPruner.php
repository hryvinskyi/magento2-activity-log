<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Retention;

use Hryvinskyi\ActivityLog\Api\ActivityPrunerInterface;

/**
 * Removes a channel's old activities in batches
 */
class ActivityPruner implements ActivityPrunerInterface
{
    /**
     * @param BatchDeleter $batchDeleter
     */
    public function __construct(
        private readonly BatchDeleter $batchDeleter
    ) {
    }

    /**
     * @inheritDoc
     */
    public function prune(string $channel, int $beforeMs, array $subjectTypes = []): int
    {
        $conditions = ['channel = ?' => $channel];
        if ($subjectTypes !== []) {
            $conditions['subject_type IN (?)'] = $subjectTypes;
        }

        return $this->batchDeleter->deleteOlderThan($beforeMs, $conditions);
    }
}
