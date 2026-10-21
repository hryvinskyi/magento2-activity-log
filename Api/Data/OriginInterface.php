<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data;

/**
 * Where an activity came from
 *
 * @api
 */
interface OriginInterface
{
    /**
     * Store the activity ran in, or null when it ran outside any store
     *
     * @return int|null
     */
    public function getStoreId(): ?int;

    /**
     * Remote IP address, or null outside an HTTP request
     *
     * @return string|null
     */
    public function getIp(): ?string;

    /**
     * User agent of the caller, or null when none was sent
     *
     * @return string|null
     */
    public function getUserAgent(): ?string;
}
