<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data;

/**
 * Kind of account an activity is attributed to
 *
 * @api
 */
enum ActorType: string
{
    case Admin = 'admin';
    case Integration = 'integration';
    case Customer = 'customer';
    case Guest = 'guest';
    case Cli = 'cli';
    case Cron = 'cron';
    case Anonymous = 'anonymous';
}
