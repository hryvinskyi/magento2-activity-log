<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data\Search;

/**
 * How the filters panel asks for a key's value
 *
 * @api
 */
enum SearchKeyControl: string
{
    /** A free text box */
    case Text = 'text';

    /** A list of the key's options */
    case Select = 'select';
}
