<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Dashboard;

use Hryvinskyi\ActivityLog\Api\Data\DashboardQueryInterface;
use Psr\Log\LoggerInterface;

/**
 * Everything the dashboard shows for one query; a widget that fails is reported as failed and the rest still load
 */
class DashboardData
{
    /**
     * @param Summary $summary
     * @param WidgetPool $widgetPool
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Summary $summary,
        private readonly WidgetPool $widgetPool,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Summary and widget data for the period and channels asked for
     *
     * @param DashboardQueryInterface $query
     * @return array{periodDays: int, channels: list<string>, summary: list<array<string, mixed>>,
     *     widgets: array<string, array{status: string, data?: array<string, mixed>}>}
     */
    public function forQuery(DashboardQueryInterface $query): array
    {
        $widgets = [];
        foreach ($this->widgetPool->visible() as $widget) {
            try {
                $widgets[$widget->getCode()] = ['status' => 'ok', 'data' => $widget->getData($query)];
            } catch (\Throwable $exception) {
                $this->logger->error(
                    sprintf('Activity dashboard widget "%s" failed.', $widget->getCode()),
                    ['exception' => $exception]
                );
                $widgets[$widget->getCode()] = ['status' => 'error'];
            }
        }

        return [
            'periodDays' => $query->getPeriodDays(),
            'channels' => $query->getChannels(),
            'summary' => $this->summary->cards($query),
            'widgets' => $widgets,
        ];
    }
}
