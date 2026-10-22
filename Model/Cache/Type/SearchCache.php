<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Cache\Type;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

/**
 * Cache type "Activity Log Search": the values the search bar suggests, cleaned on its own from Cache Management
 */
class SearchCache extends TagScope
{
    /**
     * Cache type code, unique among all cache types
     */
    public const TYPE_IDENTIFIER = 'hryvinskyi_activity_log_search';

    /**
     * Tag every entry of the type carries, which scopes cleaning to the type
     */
    public const CACHE_TAG = 'HRYVINSKYI_ACTIVITY_SEARCH';

    /**
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
