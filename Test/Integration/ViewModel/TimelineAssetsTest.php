<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\ViewModel;

use Hryvinskyi\ActivityLog\ViewModel\TimelineAssets;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The config the timeline is printed with, built by the real view model from the container
 *
 * The timeline refuses to draw when a word it writes is missing from this config, so the wording of the split diff it
 * draws long values with must be here, and the wording of the clipped diff it no longer draws must not.
 */
#[AppArea('adminhtml')]
class TimelineAssetsTest extends TestCase
{
    public function testTheWordingCarriesTheLabelsOfTheSplitDiff(): void
    {
        $wording = $this->assets()->config()['wording'];

        self::assertSame('Show %1 unchanged lines', $wording['foldLines'] ?? null);
        self::assertSame('No line', $wording['noLine'] ?? null);
        self::assertSame('%1 → %2 chars · %3 edit', $wording['longMetaOne'] ?? null);
    }

    public function testTheWordingOfTheClippedDiffIsGone(): void
    {
        $wording = $this->assets()->config()['wording'];

        self::assertArrayNotHasKey('showFullDiff', $wording);
        self::assertArrayNotHasKey('collapse', $wording);
    }

    public function testThePrintedJsonIsTheSameConfig(): void
    {
        $assets = $this->assets();

        self::assertSame($assets->config(), json_decode($assets->configJson(), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @return TimelineAssets
     */
    private function assets(): TimelineAssets
    {
        $assets = Bootstrap::getObjectManager()->create(TimelineAssets::class);
        self::assertInstanceOf(TimelineAssets::class, $assets);

        return $assets;
    }
}
