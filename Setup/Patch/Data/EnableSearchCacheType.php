<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Setup\Patch\Data;

use Hryvinskyi\ActivityLog\Model\Cache\Type\SearchCache;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Turns the module's search cache type on when the module is added to, or upgraded on, an existing store
 *
 * A fresh installation enables every cache type, but an upgrade leaves a newly added type disabled, and a disabled
 * type caches nothing: every load of the log page would read the suggestion lists from the journal again. Magento
 * records an applied patch, so this runs once; a merchant who disables the type afterwards keeps that choice.
 */
class EnableSearchCacheType implements DataPatchInterface
{
    /**
     * @param Manager $cacheManager
     */
    public function __construct(private readonly Manager $cacheManager)
    {
    }

    /**
     * Enable the module's search cache type
     *
     * @return $this
     */
    public function apply(): self
    {
        $this->cacheManager->setEnabled([SearchCache::TYPE_IDENTIFIER], true);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
