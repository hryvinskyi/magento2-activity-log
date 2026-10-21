<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Correlation;

/**
 * A correlation id the caller sent with the request, so a client can follow its own call through the journal
 */
interface IncomingIdInterface
{
    /**
     * The id the caller sent, or null when it sent none
     *
     * @return string|null
     */
    public function read(): ?string;
}
