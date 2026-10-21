<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data;

/**
 * How an activity ended
 *
 * "Denied" is an authorization refusal and is kept apart from "failure": nothing ran, so nothing can have changed.
 *
 * @api
 */
enum Outcome: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Denied = 'denied';
}
