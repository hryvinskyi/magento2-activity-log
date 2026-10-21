<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Correlation;

/**
 * 32 lowercase hex characters from the system's CSPRNG
 */
class RandomIdGenerator implements IdGeneratorInterface
{
    /**
     * @inheritDoc
     */
    public function next(): string
    {
        return bin2hex(random_bytes(16));
    }
}
