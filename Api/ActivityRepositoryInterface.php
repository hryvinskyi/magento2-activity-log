<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

use Hryvinskyi\ActivityLog\Api\Data\ActivityInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Reads and removes recorded activities
 *
 * @api
 */
interface ActivityRepositoryInterface
{
    /**
     * Load one activity
     *
     * @param int $id
     * @return ActivityInterface
     * @throws NoSuchEntityException When no activity has that id (deleted, or removed by retention)
     */
    public function get(int $id): ActivityInterface;

    /**
     * Every activity of one operation, oldest first
     *
     * @param string $correlationId
     * @return list<ActivityInterface>
     */
    public function getByCorrelationId(string $correlationId): array;

    /**
     * Delete activities; channel detail rows go with them. Other activities of the same operation are kept.
     *
     * @param list<int> $ids
     * @return int Number of activities deleted
     */
    public function deleteByIds(array $ids): int;
}
