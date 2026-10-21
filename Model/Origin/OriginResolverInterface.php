<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Origin;

use Hryvinskyi\ActivityLog\Api\Data\OriginInterface;

/**
 * Where an activity happening now comes from
 */
interface OriginResolverInterface
{
    /**
     * Origin of the current request or process
     *
     * @return OriginInterface
     */
    public function current(): OriginInterface;
}
