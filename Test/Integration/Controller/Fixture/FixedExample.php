<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Controller\Fixture;

use Hryvinskyi\ActivityLog\Api\Data\Search\RangePreset;
use Hryvinskyi\ActivityLog\Api\Search\SearchExampleInterface;

/**
 * An example search a test contributes
 */
class FixedExample implements SearchExampleInterface
{
    /**
     * @param string $title
     * @param list<array{key: string, value: string}> $chips
     * @param RangePreset $preset
     */
    public function __construct(
        private readonly string $title,
        private readonly array $chips,
        private readonly RangePreset $preset
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @inheritDoc
     */
    public function getChips(): array
    {
        return $this->chips;
    }

    /**
     * @inheritDoc
     */
    public function getPreset(): RangePreset
    {
        return $this->preset;
    }
}
