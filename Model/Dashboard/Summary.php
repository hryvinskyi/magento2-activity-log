<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Dashboard;

use Hryvinskyi\ActivityLog\Api\ActivityStatisticsInterface;
use Hryvinskyi\ActivityLog\Api\Data\DashboardQueryInterface;

/**
 * The summary cards: this period against the one before it
 *
 * The tone of a change follows what is good for the store: more activity and shorter durations are good; a rising
 * failure rate and more denied attempts are bad.
 */
class Summary
{
    /**
     * @param ActivityStatisticsInterface $statistics
     */
    public function __construct(
        private readonly ActivityStatisticsInterface $statistics
    ) {
    }

    /**
     * The summary cards of the period, each compared with the period before
     *
     * One summary card
     *
     * The summary cards of the period, each compared with the period before
     *
     * @param DashboardQueryInterface $query
     * @return list<array{key: string, label: string, value: string, unit: string, delta: string,
     *     direction: string, tone: string, empty: bool}>
     */
    public function cards(DashboardQueryInterface $query): array
    {
        $previous = $query->getPreviousRange();
        $now = $this->statistics->totals($query->getChannels(), $query->getFromMs(), $query->getToMs());
        $before = $this->statistics->totals($query->getChannels(), $previous['fromMs'], $previous['toMs']);
        $empty = $now['total'] === 0;

        return [
            $this->card(
                'total',
                (string) __('Total activities'),
                (float) $now['total'],
                (float) $before['total'],
                '',
                'up',
                $empty
            ),
            $this->card(
                'duration',
                (string) __('Avg duration'),
                $now['avgDurationMs'] ?? 0.0,
                $before['avgDurationMs'] ?? 0.0,
                (string) __('ms'),
                'down',
                $empty || $now['avgDurationMs'] === null
            ),
            $this->card(
                'failure_rate',
                (string) __('Failure rate'),
                $this->rate($now['failures'], $now['total']),
                $this->rate($before['failures'], $before['total']),
                '%',
                'down',
                $empty,
                1
            ),
            $this->card(
                'denied',
                (string) __('Denied attempts'),
                (float) $now['denied'],
                (float) $before['denied'],
                '',
                'down',
                $empty
            ),
        ];
    }

    /**
     * One summary card
     *
     * @param string $key
     * @param string $label
     * @param float $value
     * @param float $previous
     * @param string $unit
     * @param string $goodDirection "up" or "down"
     * @param bool $empty
     * @param int $decimals
     * @return array{key: string, label: string, value: string, unit: string, delta: string,
     *     direction: string, tone: string, empty: bool}
     */
    private function card(
        string $key,
        string $label,
        float $value,
        float $previous,
        string $unit,
        string $goodDirection,
        bool $empty,
        int $decimals = 0
    ): array {
        $change = $value - $previous;
        $direction = match (true) {
            abs($change) < 0.0001 => 'flat',
            $change > 0 => 'up',
            default => 'down',
        };

        return [
            'key' => $key,
            'label' => $label,
            'value' => number_format($value, $decimals),
            'unit' => $unit,
            'delta' => $this->delta($value, $previous, $unit, $decimals),
            'direction' => $direction,
            'tone' => $direction === 'flat' ? 'neutral' : ($direction === $goodDirection ? 'good' : 'bad'),
            'empty' => $empty,
        ];
    }

    /**
     * Share of the total, in percent
     *
     * "+12%" for counts and durations, "+0.4 pt" for rates
     *
     * @param float $value
     * @param float $previous
     * @param string $unit
     * @param int $decimals
     * @return string
     */
    private function delta(float $value, float $previous, string $unit, int $decimals): string
    {
        $sign = $value >= $previous ? '+' : '−';
        if ($unit === '%') {
            return $sign . number_format(abs($value - $previous), $decimals) . ' pt';
        }

        if ($previous <= 0.0) {
            return $value > 0.0 ? (string) __('new') : '0%';
        }

        return $sign . number_format(abs($value - $previous) / $previous * 100) . '%';
    }

    /**
     * Share of the total, in percent
     *
     * @param int $part
     * @param int $total
     * @return float Percent
     */
    private function rate(int $part, int $total): float
    {
        return $total > 0 ? $part / $total * 100 : 0.0;
    }
}
