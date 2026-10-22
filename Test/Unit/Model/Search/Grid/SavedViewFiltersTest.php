<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Search\Grid;

use Hryvinskyi\ActivityLog\Model\Search\Grid\SavedViewFilters;
use PHPUnit\Framework\TestCase;

/**
 * Saved grid states lose their filters and keyword search and keep everything else
 */
class SavedViewFiltersTest extends TestCase
{
    public function testTheCurrentStateAndEveryViewLoseFiltersAndSearch(): void
    {
        $columns = ['channel' => ['visible' => true, 'sorting' => false]];
        $paging = ['pageSize' => 50, 'current' => 3];

        $cleaned = (new SavedViewFilters())->withoutFilters([
            'current' => [
                'columns' => $columns,
                'filters' => ['applied' => ['placeholder' => true, 'channel' => 'admin']],
                'search' => ['value' => 'jane'],
                'paging' => $paging,
            ],
            'views' => [
                'default' => [
                    'label' => 'Default View',
                    'data' => ['filters' => ['applied' => []], 'paging' => $paging],
                ],
                'mine' => [
                    'label' => 'Mine',
                    'data' => ['columns' => $columns, 'filters' => ['applied' => ['created_at' => ['from' => '1']]]],
                ],
                'broken' => 'not a view',
            ],
        ]);

        self::assertSame([
            'current' => ['columns' => $columns, 'paging' => $paging],
            'views' => [
                'default' => ['label' => 'Default View', 'data' => ['paging' => $paging]],
                'mine' => ['label' => 'Mine', 'data' => ['columns' => $columns]],
                'broken' => 'not a view',
            ],
        ], $cleaned);
    }

    public function testAStateWithoutFiltersIsLeftAsItIs(): void
    {
        $config = ['current' => ['columns' => [], 'displayMode' => 'grid'], 'other' => ['filters' => 1]];

        self::assertSame($config, (new SavedViewFilters())->withoutFilters($config));
    }
}
