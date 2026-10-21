<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Dashboard\Widget;

use Hryvinskyi\ActivityLog\Api\ActivityStatisticsInterface;
use Hryvinskyi\ActivityLog\Api\DashboardWidgetInterface;
use Hryvinskyi\ActivityLog\Api\Data\DashboardQueryInterface;
use Hryvinskyi\ActivityLog\Api\Data\WidgetViewType;
use Hryvinskyi\ActivityLog\Model\Channel\ChannelPresenter;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Activities per day, stacked by channel
 */
class VolumeWidget implements DashboardWidgetInterface
{
    /**
     * @param ActivityStatisticsInterface $statistics
     * @param ChannelPresenter $channelPresenter
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly ActivityStatisticsInterface $statistics,
        private readonly ChannelPresenter $channelPresenter,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return 'volume';
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return (string) __('Activity volume');
    }

    /**
     * @inheritDoc
     */
    public function getSubtitle(): ?string
    {
        return (string) __('Activities per day, by channel');
    }

    /**
     * @inheritDoc
     */
    public function getModuleTag(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getViewType(): WidgetViewType
    {
        return WidgetViewType::Bar;
    }

    /**
     * @inheritDoc
     */
    public function getSpan(): int
    {
        return 2;
    }

    /**
     * @inheritDoc
     */
    public function getAclResource(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getData(DashboardQueryInterface $query): array
    {
        $volume = $this->statistics->volumeByDay($query);
        $channels = $this->channelPresenter->present();

        $datasets = [];
        $hasData = false;
        foreach ($volume['counts'] as $code => $counts) {
            $hasData = $hasData || array_sum($counts) > 0;
            $datasets[] = [
                'label' => $channels[$code]['label'] ?? $code,
                'color' => $channels[$code]['chart'] ?? '#8C8C8C',
                'values' => $counts,
            ];
        }

        return [
            'labels' => array_map(fn (string $day): string => $this->dayLabel($day), $volume['days']),
            'datasets' => $hasData ? $datasets : [],
            'stacked' => true,
        ];
    }

    /**
     * "Oct 14"
     *
     * @param string $day Y-m-d
     * @return string
     */
    private function dayLabel(string $day): string
    {
        return $this->timezone->formatDateTime(
            $day,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::NONE,
            null,
            'UTC',
            'MMM d'
        );
    }
}
