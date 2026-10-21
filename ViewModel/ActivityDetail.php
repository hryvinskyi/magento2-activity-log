<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\ViewModel;

use Hryvinskyi\ActivityLog\Api\Data\ActivityInterface;
use Hryvinskyi\ActivityLog\Model\Channel\ChannelPresenter;
use Hryvinskyi\ActivityLog\Model\Presentation\ActorTypeLabels;
use Hryvinskyi\ActivityLog\Model\Presentation\DurationScale;
use Hryvinskyi\ActivityLog\Model\Presentation\TimeFormatter;
use Hryvinskyi\ActivityLog\Model\Source\OutcomeOptions;
use Hryvinskyi\ActivityLog\Model\View\CurrentActivity;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The header and facts of the activity detail page
 */
class ActivityDetail implements ArgumentInterface
{
    public const URL_PATH_GRID = 'activitylog/activity/index';
    public const URL_PATH_DELETE = 'activitylog/activity/delete';

    /**
     * @param CurrentActivity $currentActivity
     * @param ChannelPresenter $channelPresenter
     * @param DurationScale $durationScale
     * @param TimeFormatter $timeFormatter
     * @param ActorTypeLabels $actorTypeLabels
     * @param OutcomeOptions $outcomeOptions
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param AuthorizationInterface $authorization
     * @param JsonHexTag $json
     */
    public function __construct(
        private readonly CurrentActivity $currentActivity,
        private readonly ChannelPresenter $channelPresenter,
        private readonly DurationScale $durationScale,
        private readonly TimeFormatter $timeFormatter,
        private readonly ActorTypeLabels $actorTypeLabels,
        private readonly OutcomeOptions $outcomeOptions,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization,
        private readonly JsonHexTag $json
    ) {
    }

    /**
     * The activity shown, or null when it does not exist
     *
     * @return ActivityInterface|null
     */
    public function activity(): ?ActivityInterface
    {
        return $this->currentActivity->get();
    }

    /**
     * The id the page was asked for
     *
     * @return int|null
     */
    public function requestedId(): ?int
    {
        return $this->currentActivity->requestedId();
    }

    /**
     * Label of how the activity ended
     *
     * When the activity happened, as a date, a relative time and the timezone
     *
     * Label of how the activity ended
     *
     * @param ActivityInterface $activity
     * @return string
     */
    public function outcomeLabel(ActivityInterface $activity): string
    {
        return (string) $this->outcomeOptions->label($activity->getOutcome());
    }

    /**
     * When the activity happened, as a date, a relative time and the timezone
     *
     * @param ActivityInterface $activity
     * @return array{dateTime: string, relative: string, timezone: string}
     */
    public function time(ActivityInterface $activity): array
    {
        return [
            'dateTime' => $this->timeFormatter->dateTime($activity->getOccurredAtMs()),
            'relative' => (string) $this->timeFormatter->relative($activity->getOccurredAtMs()),
            'timezone' => $this->timeFormatter->timezoneName(),
        ];
    }

    /**
     * Duration text and its place on the duration scale; an activity without a duration of its own has no level
     *
     * @param ActivityInterface $activity
     * @return array{text: string, level: string|null, color: string|null, textColor: string|null, note: string}
     */
    public function duration(ActivityInterface $activity): array
    {
        $durationMs = $activity->getDurationMs();
        $level = $this->durationScale->levelOf($durationMs);
        if ($durationMs === null || $level === null) {
            return [
                'text' => '—',
                'level' => null,
                'color' => null,
                'textColor' => null,
                'note' => (string) __('part of the parent call'),
            ];
        }

        return [
            'text' => (string) __('%1 ms', number_format($durationMs, 0)),
            'level' => $level['key'],
            'color' => $level['color'],
            'textColor' => $level['text'],
            'note' => $level['label'],
        ];
    }

    /**
     * Label of the kind of account the activity is attributed to
     *
     * Badge of a channel code: label and colours
     *
     * @param string $code
     * @return array{label: string, bg: string, fg: string, chart: string}
     */
    public function channel(string $code): array
    {
        $channel = $this->channelPresenter->present()[$code] ?? null;

        return $channel ?? ['label' => $code, 'bg' => '#ECECEC', 'fg' => '#4D4D4D', 'chart' => '#8C8C8C'];
    }

    /**
     * Label of the kind of account the activity is attributed to
     *
     * @param ActivityInterface $activity
     * @return string
     */
    public function actorTypeLabel(ActivityInterface $activity): string
    {
        return (string) $this->actorTypeLabels->label($activity->getActor()->getType());
    }

    /**
     * The grid filtered to this activity's actor
     *
     * @param ActivityInterface $activity
     * @return string
     */
    public function actorUrl(ActivityInterface $activity): string
    {
        return $this->gridUrl(['actor_name' => $activity->getActor()->getName()]);
    }

    /**
     * Name of the store view the activity ran in, or null
     *
     * @param ActivityInterface $activity
     * @return string|null
     */
    public function storeName(ActivityInterface $activity): ?string
    {
        $storeId = $activity->getOrigin()->getStoreId();
        if ($storeId === null) {
            return null;
        }

        try {
            return (string) $this->storeManager->getStore($storeId)->getName();
        } catch (NoSuchEntityException) {
            return (string) __('Store #%1', $storeId);
        }
    }

    /**
     * The grid, optionally filtered
     *
     * @param array<string,string> $filters
     * @return string
     */
    public function gridUrl(array $filters = []): string
    {
        return $this->urlBuilder->getUrl(self::URL_PATH_GRID, $filters === [] ? [] : ['_query' => $filters]);
    }

    /**
     * The grid filtered to this activity's operation
     *
     * @param ActivityInterface $activity
     * @return string
     */
    public function relatedUrl(ActivityInterface $activity): string
    {
        return $this->gridUrl(['correlation_id' => $activity->getCorrelationId()]);
    }

    /**
     * Where the delete button posts to
     *
     * Whether the admin user may delete activities
     *
     * @return bool
     */
    public function canDelete(): bool
    {
        return $this->authorization->isAllowed('Hryvinskyi_ActivityLog::delete');
    }

    /**
     * Where the delete button posts to
     *
     * @param ActivityInterface $activity
     * @return string
     */
    public function deleteUrl(ActivityInterface $activity): string
    {
        return $this->urlBuilder->getUrl(self::URL_PATH_DELETE, ['id' => $activity->getId()]);
    }

    /**
     * Script config of the button that copies the correlation id, for data-mage-init
     *
     * @param ActivityInterface $activity
     * @return string
     */
    public function copyCorrelationInit(ActivityInterface $activity): string
    {
        return (string) $this->json->serialize([
            'Hryvinskyi_ActivityLog/js/view/copy-text' => ['text' => $activity->getCorrelationId()],
        ]);
    }

    /**
     * Script config of the delete form's confirmation, for data-mage-init
     *
     * @return string
     */
    public function deleteConfirmInit(): string
    {
        return (string) $this->json->serialize([
            'Hryvinskyi_ActivityLog/js/view/confirm-submit' => [
                'title' => (string) __('Delete this activity?'),
                'content' => (string) __(
                    'It is removed from the journal permanently. Other activities of the same operation are kept.'
                ),
            ],
        ]);
    }
}
