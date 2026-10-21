<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Presentation;

/**
 * The one duration scale every screen uses: good, slow, critical
 *
 * The browser code reads the same levels from the page, so the grid, the detail page and the dashboard cannot
 * disagree about where "slow" starts.
 */
class DurationScale
{
    /**
     * @param array<string,array{below:int|null,color:string,text:string,label:string}> $levels
     *     In rising order; the last level has no upper bound
     */
    public function __construct(
        private readonly array $levels = [
            'good' => ['below' => 100, 'color' => '#3C9D5A', 'text' => '#1E7B3C', 'label' => '< 100 ms'],
            'slow' => ['below' => 1000, 'color' => '#E6A117', 'text' => '#7A5A00', 'label' => '100–999 ms'],
            'critical' => ['below' => null, 'color' => '#C8372D', 'text' => '#B0251D', 'label' => '≥ 1000 ms'],
        ]
    ) {
    }

    /**
     * The levels as the browser code reads them
     *
     * @return list<array{key: string, below: int|null, color: string, text: string, label: string}>
     */
    public function levels(): array
    {
        $levels = [];
        foreach ($this->levels as $key => $level) {
            $levels[] = ['key' => $key] + $level;
        }

        return $levels;
    }

    /**
     * The level a duration falls in, or null when there is no duration
     *
     * @param float|null $durationMs
     * @return array{key: string, below: int|null, color: string, text: string, label: string}|null
     */
    public function levelOf(?float $durationMs): ?array
    {
        if ($durationMs === null || $durationMs < 0) {
            return null;
        }

        foreach ($this->levels() as $level) {
            if ($level['below'] === null || $durationMs < $level['below']) {
                return $level;
            }
        }

        return null;
    }
}
