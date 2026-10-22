<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data\Search;

/**
 * Which group of the search bar's suggestions a key's values are offered in, in the order the groups are shown
 *
 * The search page prints a translated title for each code. Values of keys in "other" are offered only once their
 * key is typed ("channel: "), not for plain words.
 *
 * @api
 */
enum SearchKeyGroup: string
{
    /** Names of the fields and settings that changed */
    case Fields = 'fields';

    /** The records that changed */
    case Records = 'records';

    /** Who acted, and through which client */
    case People = 'people';

    /** Kinds of record */
    case Types = 'types';

    /** Everything else */
    case Other = 'other';
}
