<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data\Search;

/**
 * How the before and after values of a changed field are shown
 *
 * Short values sit inline; long text is shown as a split diff of its lines with the changed words marked; JSON
 * arrays as added, removed and unchanged items; masked values are never shown, only marked.
 *
 * @api
 */
enum ValueKind: string
{
    case Short = 'short';
    case Long = 'long';
    case Json = 'json';
    case Masked = 'masked';
}
