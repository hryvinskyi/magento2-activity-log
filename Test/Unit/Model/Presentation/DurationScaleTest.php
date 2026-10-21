<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Presentation;

use Hryvinskyi\ActivityLog\Model\Presentation\DurationScale;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DurationScaleTest extends TestCase
{
    /**
     * @return array<string, array{float, string}>
     */
    public static function durations(): array
    {
        return [
            'instant' => [0.0, 'good'],
            'just under slow' => [99.9, 'good'],
            'slow starts at 100' => [100.0, 'slow'],
            'just under critical' => [999.9, 'slow'],
            'critical starts at 1000' => [1000.0, 'critical'],
            'very long' => [65000.0, 'critical'],
        ];
    }

    #[DataProvider('durations')]
    public function testPlacesADurationOnTheScale(float $durationMs, string $level): void
    {
        self::assertSame($level, (new DurationScale())->levelOf($durationMs)['key'] ?? null);
    }

    public function testNoDurationHasNoLevel(): void
    {
        self::assertNull((new DurationScale())->levelOf(null));
    }

    public function testTheBrowserGetsTheSameLevels(): void
    {
        $keys = array_column((new DurationScale())->levels(), 'key');

        self::assertSame(['good', 'slow', 'critical'], $keys);
    }
}
