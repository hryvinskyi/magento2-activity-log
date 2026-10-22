<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\ViewModel;

use Hryvinskyi\ActivityLog\Api\ActivityRepositoryInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActivityInterface;
use Hryvinskyi\ActivityLog\Model\Channel\ChannelPresenter;
use Hryvinskyi\ActivityLog\Model\Presentation\TimeFormatter;
use Hryvinskyi\ActivityLog\Model\Source\OutcomeOptions;
use Hryvinskyi\ActivityLog\Model\View\CurrentActivity;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * "Same operation": every activity sharing the shown activity's correlation id, oldest first, with offsets from
 * the one that started it
 */
class Operation implements ArgumentInterface
{
    public const URL_PATH_VIEW = 'hryvinskyi_activitylog/activity/view';

    /**
     * @var list<ActivityInterface>|null
     */
    private ?array $activities = null;

    /**
     * @param CurrentActivity $currentActivity
     * @param ActivityRepositoryInterface $activityRepository
     * @param ChannelPresenter $channelPresenter
     * @param OutcomeOptions $outcomeOptions
     * @param TimeFormatter $timeFormatter
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly CurrentActivity $currentActivity,
        private readonly ActivityRepositoryInterface $activityRepository,
        private readonly ChannelPresenter $channelPresenter,
        private readonly OutcomeOptions $outcomeOptions,
        private readonly TimeFormatter $timeFormatter,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Number of activities in the operation, the shown one included
     *
     * @return int
     */
    public function size(): int
    {
        return count($this->activities());
    }

    /**
     * From the start of the operation to the end of its last activity, in milliseconds
     *
     * @return float
     */
    public function totalMs(): float
    {
        $activities = $this->activities();
        if ($activities === []) {
            return 0.0;
        }

        $first = $activities[0];
        $end = $first->getOccurredAtMs() + ($first->getDurationMs() ?? 0.0);
        foreach ($activities as $activity) {
            $end = max($end, $activity->getOccurredAtMs() + ($activity->getDurationMs() ?? 0.0));
        }

        return $end - $first->getOccurredAtMs();
    }

    /**
     * Timeline items, oldest first
     *
     * @return list<array{
     *     id: int|null, url: string, current: bool, offset: string, clock: string, action: string,
     *     subject: string, outcome: string, outcomeLabel: string,
     *     channel: array{label: string, bg: string, fg: string, chart: string}
     * }>
     */
    public function items(): array
    {
        $current = $this->currentActivity->get();
        $activities = $this->activities();
        if ($current === null || $activities === []) {
            return [];
        }

        $start = $activities[0]->getOccurredAtMs();
        $channels = $this->channelPresenter->present();
        $items = [];
        foreach ($activities as $index => $activity) {
            $offset = $activity->getOccurredAtMs() - $start;
            $items[] = [
                'id' => $activity->getId(),
                'url' => $this->urlBuilder->getUrl(self::URL_PATH_VIEW, ['id' => $activity->getId()]),
                'current' => $activity->getId() === $current->getId(),
                'offset' => $index === 0 ? (string) __('+0 ms') : (string) __('+%1 ms', number_format($offset)),
                'clock' => $this->timeFormatter->clock($activity->getOccurredAtMs()),
                'action' => $activity->getAction(),
                'subject' => $activity->getSubject()?->getLabel() ?? '',
                'outcome' => $activity->getOutcome()->value,
                'outcomeLabel' => (string) $this->outcomeOptions->label($activity->getOutcome()),
                'channel' => $channels[$activity->getChannel()] ?? [
                    'label' => $activity->getChannel(),
                    'bg' => '#ECECEC',
                    'fg' => '#4D4D4D',
                    'chart' => '#8C8C8C',
                ],
            ];
        }

        return $items;
    }

    /**
     * The activities of the operation, loaded once
     *
     * @return list<ActivityInterface>
     */
    private function activities(): array
    {
        if ($this->activities !== null) {
            return $this->activities;
        }

        $current = $this->currentActivity->get();

        return $this->activities = $current === null
            ? []
            : $this->activityRepository->getByCorrelationId($current->getCorrelationId());
    }
}
