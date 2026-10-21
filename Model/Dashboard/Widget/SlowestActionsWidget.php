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
use Hryvinskyi\ActivityLog\Model\Presentation\DurationScale;

/**
 * The ten actions with the highest average duration, coloured on the duration scale
 */
class SlowestActionsWidget implements DashboardWidgetInterface
{
    private const LIMIT = 10;

    /**
     * @param ActivityStatisticsInterface $statistics
     * @param DurationScale $durationScale
     */
    public function __construct(
        private readonly ActivityStatisticsInterface $statistics,
        private readonly DurationScale $durationScale
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return 'slowest_actions';
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return (string) __('Slowest actions');
    }

    /**
     * @inheritDoc
     */
    public function getSubtitle(): ?string
    {
        return (string) __('Average duration, top %1', self::LIMIT);
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
        return 1;
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
        $actions = $this->statistics->slowestActions($query, self::LIMIT);
        $labels = [];
        $values = [];
        $colors = [];
        foreach ($actions as $action) {
            $labels[] = $action['action'];
            $values[] = round($action['avgDurationMs']);
            $colors[] = $this->durationScale->levelOf($action['avgDurationMs'])['color'] ?? '#8C8C8C';
        }

        return [
            'labels' => $labels,
            'datasets' => $values === [] ? [] : [
                ['label' => (string) __('Average duration'), 'color' => $colors, 'values' => $values],
            ],
            'horizontal' => true,
            'unit' => 'ms',
        ];
    }
}
