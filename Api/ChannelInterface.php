<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

/**
 * A source of activities, registered by the module that records them
 *
 * Register an implementation in the "channels" argument of the channel pool under its code.
 *
 * @api
 */
interface ChannelInterface
{
    /**
     * Code stored with every activity of the channel: lowercase letters, digits, underscore
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Label shown on badges and filters
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Colour of the channel's series in charts, as #RRGGBB
     *
     * @return string
     */
    public function getChartColor(): string;

    /**
     * Badge background, as #RRGGBB
     *
     * @return string
     */
    public function getBadgeBackground(): string;

    /**
     * Badge text colour, as #RRGGBB
     *
     * @return string
     */
    public function getBadgeForeground(): string;

    /**
     * Days the journal keeps the channel's activities, or null when the channel's module removes them itself
     *
     * @return int|null
     */
    public function getRetentionDays(): ?int;
}
