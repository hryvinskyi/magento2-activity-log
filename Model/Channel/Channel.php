<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Channel;

use Hryvinskyi\ActivityLog\Api\ChannelInterface;
use Hryvinskyi\ActivityLog\Model\Data\Activity;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * A channel described in di.xml: a module declares a virtual type of this class with its code, label, colours and
 * the config path of its retention; an empty path means the module purges the channel itself
 */
class Channel implements ChannelInterface
{
    private const COLOR_PATTERN = '/^#[0-9A-Fa-f]{6}$/';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param string $code
     * @param string $label
     * @param string $chartColor
     * @param string $badgeBackground
     * @param string $badgeForeground
     * @param string $retentionConfigPath
     * @throws \InvalidArgumentException When the declaration is malformed
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly string $code,
        private readonly string $label,
        private readonly string $chartColor = '#8C8C8C',
        private readonly string $badgeBackground = '#ECECEC',
        private readonly string $badgeForeground = '#4D4D4D',
        private readonly string $retentionConfigPath = ''
    ) {
        if (preg_match(Activity::CHANNEL_PATTERN, $code) !== 1) {
            throw new \InvalidArgumentException(sprintf('Malformed channel code "%s".', $code));
        }

        foreach ([$chartColor, $badgeBackground, $badgeForeground] as $color) {
            if (preg_match(self::COLOR_PATTERN, $color) !== 1) {
                throw new \InvalidArgumentException(
                    sprintf('Channel "%s": "%s" is not a #RRGGBB colour.', $code, $color)
                );
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @inheritDoc
     */
    public function getChartColor(): string
    {
        return $this->chartColor;
    }

    /**
     * @inheritDoc
     */
    public function getBadgeBackground(): string
    {
        return $this->badgeBackground;
    }

    /**
     * @inheritDoc
     */
    public function getBadgeForeground(): string
    {
        return $this->badgeForeground;
    }

    /**
     * @inheritDoc
     */
    public function getRetentionDays(): ?int
    {
        if ($this->retentionConfigPath === '') {
            return null;
        }

        $days = $this->scopeConfig->getValue($this->retentionConfigPath);

        return is_numeric($days) && (int) $days > 0 ? (int) $days : null;
    }
}
