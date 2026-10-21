<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

/**
 * Activity log settings
 *
 * @api
 */
interface ConfigInterface
{
    /**
     * Whether activities are recorded at all
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Field names (or parts of field names) whose values are masked, lowercase
     *
     * @return list<string>
     */
    public function getSecretFields(): array;

    /**
     * Days to keep activities of a channel no installed module registers any more
     *
     * @return int
     */
    public function getOrphanRetentionDays(): int;
}
