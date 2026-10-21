<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Correlation;

/**
 * Draws new correlation ids
 */
interface IdGeneratorInterface
{
    /**
     * A new, unguessable correlation id
     *
     * @return string
     */
    public function next(): string;
}
