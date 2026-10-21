<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Presentation;

use Hryvinskyi\ActivityLog\Api\MillisecondClockInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Activity times as people read them: in the store timezone, with milliseconds, and how long ago
 */
class TimeFormatter
{
    private const DATE_TIME_PATTERN = 'MMM d, y h:mm:ss';
    private const MERIDIEM_PATTERN = 'a';
    private const CLOCK_PATTERN = 'HH:mm:ss';

    /**
     * @param TimezoneInterface $timezone
     * @param MillisecondClockInterface $clock
     */
    public function __construct(
        private readonly TimezoneInterface $timezone,
        private readonly MillisecondClockInterface $clock
    ) {
    }

    /**
     * "Oct 20, 2026 2:32:08.120 PM"
     *
     * @param int $occurredAtMs
     * @return string
     */
    public function dateTime(int $occurredAtMs): string
    {
        return $this->format($occurredAtMs, self::DATE_TIME_PATTERN) . $this->milliseconds($occurredAtMs)
            . ' ' . $this->format($occurredAtMs, self::MERIDIEM_PATTERN);
    }

    /**
     * "14:32:08.158", for lines that already show the date
     *
     * @param int $occurredAtMs
     * @return string
     */
    public function clock(int $occurredAtMs): string
    {
        return $this->format($occurredAtMs, self::CLOCK_PATTERN) . $this->milliseconds($occurredAtMs);
    }

    /**
     * Name of the store timezone, "Europe/Copenhagen"
     *
     * @return string
     */
    public function timezoneName(): string
    {
        return $this->timezone->getConfigTimezone();
    }

    /**
     * "13 minutes ago"
     *
     * @param int $occurredAtMs
     * @return Phrase
     */
    public function relative(int $occurredAtMs): Phrase
    {
        $seconds = max(0, intdiv($this->clock->nowMs() - $occurredAtMs, 1000));

        return match (true) {
            $seconds < 60 => __('just now'),
            $seconds < 3600 => __('%1 minutes ago', intdiv($seconds, 60)),
            $seconds < 86400 => __('%1 hours ago', intdiv($seconds, 3600)),
            default => __('%1 days ago', intdiv($seconds, 86400)),
        };
    }

    /**
     * ".038": the store's date API works in whole seconds, so milliseconds are appended
     *
     * @param int $occurredAtMs
     * @return string
     */
    private function milliseconds(int $occurredAtMs): string
    {
        return sprintf('.%03d', $occurredAtMs % 1000);
    }

    /**
     * A moment in the store timezone with the given pattern
     *
     * @param int $occurredAtMs
     * @param string $pattern ICU pattern, whole seconds
     * @return string
     */
    private function format(int $occurredAtMs, string $pattern): string
    {
        $utc = gmdate('Y-m-d H:i:s', intdiv($occurredAtMs, 1000));

        return $this->timezone->formatDateTime(
            $this->timezone->date($utc, null, true, true),
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::MEDIUM,
            null,
            null,
            $pattern
        );
    }
}
