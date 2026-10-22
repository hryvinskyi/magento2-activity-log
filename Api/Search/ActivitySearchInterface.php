<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchPageInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterface;

/**
 * Searches the journal: the range first, then the filters on indexed columns, then value text among the newest
 * candidates only
 *
 * A filter whose key no module serves is searched as text ("key: value"). Several conditions registered for one key
 * are alternatives: an activity matches the filter when it matches any of them. Text matched outside indexes (a
 * residual condition) is looked for among the newest 10,000 candidates the other filters leave, counted from the
 * page's position; past that the page says it was narrowed instead of scanning on.
 *
 * @api
 */
interface ActivitySearchInterface
{
    /**
     * The page of matches the request asks for
     *
     * @param SearchRequestInterface $request
     * @return SearchPageInterface
     */
    public function search(SearchRequestInterface $request): SearchPageInterface;
}
