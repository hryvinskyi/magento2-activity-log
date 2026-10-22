<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\RangePreset;

/**
 * A search offered as an example when the log page has nothing to show yet
 *
 * Registered by the module that serves its keys, in the "examples" argument of
 * Hryvinskyi\ActivityLog\Model\Search\ExamplePool; its wording names nothing outside that module.
 *
 * @api
 */
interface SearchExampleInterface
{
    /**
     * What the example finds, translated
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * The filters it applies, in order
     *
     * @return list<array{key: string, value: string}>
     */
    public function getChips(): array;

    /**
     * The time range it searches
     *
     * @return RangePreset
     */
    public function getPreset(): RangePreset;
}
