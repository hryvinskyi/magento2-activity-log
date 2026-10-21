<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Actor;

/**
 * Finds the name people know an account by, for one kind of account
 */
interface AccountNameLookupInterface
{
    /**
     * The account's name, or null when the account does not exist
     *
     * @param int $id
     * @return string|null
     */
    public function nameOf(int $id): ?string;
}
