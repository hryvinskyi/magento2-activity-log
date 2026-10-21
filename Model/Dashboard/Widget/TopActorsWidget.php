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
use Magento\Framework\UrlInterface;

/**
 * Who did the most; a client acting for an admin is listed apart from the admin acting themselves
 */
class TopActorsWidget implements DashboardWidgetInterface
{
    private const LIMIT = 10;

    /**
     * @param ActivityStatisticsInterface $statistics
     * @param ChannelPresenter $channelPresenter
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly ActivityStatisticsInterface $statistics,
        private readonly ChannelPresenter $channelPresenter,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return 'top_actors';
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return (string) __('Top actors');
    }

    /**
     * @inheritDoc
     */
    public function getSubtitle(): ?string
    {
        return (string) __('By number of activities');
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
        return WidgetViewType::Table;
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
        return 'Hryvinskyi_ActivityLog::view';
    }

    /**
     * @inheritDoc
     */
    public function getData(DashboardQueryInterface $query): array
    {
        $channels = $this->channelPresenter->present();
        $rows = [];
        foreach ($this->statistics->topActors($query, self::LIMIT) as $actor) {
            $name = $actor['actorName'];
            if ($actor['viaClient'] !== null) {
                $via = $actor['viaChannel'] !== null
                    ? ($channels[$actor['viaChannel']]['label'] ?? $actor['viaChannel']) . ' · '
                    : '';
                $name = (string) __('%1 via %2%3', $actor['actorName'], $via, $actor['viaClient']);
            }

            $rows[] = [
                'cells' => [
                    'actor' => $name,
                    'count' => number_format($actor['count']),
                    'failures' => $actor['count'] > 0
                        ? number_format($actor['failures'] / $actor['count'] * 100, 1) . '%'
                        : '0%',
                ],
                'href' => $this->urlBuilder->getUrl(
                    'activitylog/activity/index',
                    ['_query' => ['actor_name' => $actor['actorName']]]
                ),
            ];
        }

        return [
            'columns' => [
                ['key' => 'actor', 'label' => (string) __('Actor')],
                ['key' => 'count', 'label' => (string) __('Activities'), 'align' => 'right'],
                ['key' => 'failures', 'label' => (string) __('Failure %'), 'align' => 'right'],
            ],
            'rows' => $rows,
        ];
    }
}
