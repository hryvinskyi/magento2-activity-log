<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Setup\Patch\Data;

use Hryvinskyi\ActivityLog\Setup\Patch\Data\EnableSearchCacheType;
use Magento\Framework\App\Cache\Manager;
use PHPUnit\Framework\TestCase;

/**
 * The patch turns on the search cache type, and only it
 */
class EnableSearchCacheTypeTest extends TestCase
{
    public function testEnablesTheSearchCacheType(): void
    {
        $cacheManager = $this->createMock(Manager::class);
        $cacheManager->expects(self::once())
            ->method('setEnabled')
            ->with(['hryvinskyi_activity_log_search'], true)
            ->willReturn(['hryvinskyi_activity_log_search']);

        $patch = new EnableSearchCacheType($cacheManager);

        self::assertSame($patch, $patch->apply());
        self::assertSame([], EnableSearchCacheType::getDependencies());
        self::assertSame([], $patch->getAliases());
    }
}
