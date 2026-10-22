<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data\Search;

/**
 * What an activity did to its subject, as the sentence of a search result says it
 *
 * A closed set of codes: the page prints the words for each in the admin's language, so the server never sends a
 * sentence. An activity without event details "performed" its action.
 *
 * @api
 */
enum Verb: string
{
    case Changed = 'changed';
    case Created = 'created';
    case Deleted = 'deleted';
    case Performed = 'performed';
    case TriedToChange = 'tried_to_change';
    case TriedToDelete = 'tried_to_delete';
}
