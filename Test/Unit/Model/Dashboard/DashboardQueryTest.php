<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Dashboard;

use Hryvinskyi\ActivityLog\Model\Data\DashboardQuery;
use PHPUnit\Framework\TestCase;

class DashboardQueryTest extends TestCase
{
    public function testThePreviousPeriodEndsWhereThisOneStarts(): void
    {
        $query = new DashboardQuery(14, ['rest', 'mcp'], 1792500000000);

        self::assertSame(1792500000000 - 14 * 86_400_000, $query->getFromMs());
        self::assertSame(
            ['fromMs' => $query->getFromMs() - 14 * 86_400_000, 'toMs' => $query->getFromMs()],
            $query->getPreviousRange()
        );
    }

    public function testRefusesAPeriodTheDashboardDoesNotOffer(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DashboardQuery(10, ['rest'], 1792500000000);
    }

    public function testRefusesNoChannels(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DashboardQuery(7, [], 1792500000000);
    }

    public function testRefusesAMalformedChannel(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DashboardQuery(7, ["rest' OR 1=1"], 1792500000000);
    }
}
