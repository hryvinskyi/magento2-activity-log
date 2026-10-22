<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ActivityLog\Api\Data\Search\EventViewInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\EventViewInterfaceFactory;
use Hryvinskyi\ActivityLog\Model\Search\Detail\EventDetails;
use Magento\Backend\Model\UrlInterface;

/**
 * Turns the journal rows of a result page into the views the page shows, in the page's order
 *
 * Besides the row itself a view gets the number of other activities its operation did (one grouped query for the
 * page), the details the recording module gives (one call per provider for the page) and its detail page's URL.
 */
class EventViewAssembler
{
    /**
     * Route of an activity's detail page
     */
    private const DETAIL_ROUTE = 'hryvinskyi_activitylog/activity/view';

    /**
     * Journal columns a view is built from, besides entity_id, occurred_at_ms and channel
     */
    private const COLUMNS = [
        'correlation_id',
        'actor_type',
        'actor_name',
        'via_client',
        'via_version',
        'via_channel',
        'action',
        'outcome',
        'message',
        'duration_ms',
        'ip',
        'subject_type',
        'subject_id',
        'subject_label',
    ];

    /**
     * @param EventViewInterfaceFactory $eventViewFactory
     * @param SiblingCounter $siblingCounter
     * @param EventDetails $eventDetails
     * @param UrlInterface $backendUrl
     */
    public function __construct(
        private readonly EventViewInterfaceFactory $eventViewFactory,
        private readonly SiblingCounter $siblingCounter,
        private readonly EventDetails $eventDetails,
        private readonly UrlInterface $backendUrl
    ) {
    }

    /**
     * Journal columns the rows passed to assemble() must hold, besides entity_id, occurred_at_ms and channel
     *
     * @return list<string>
     */
    public function columns(): array
    {
        return self::COLUMNS;
    }

    /**
     * One view per row, in the rows' order
     *
     * @param list<array<string,mixed>> $rows Journal rows with entity_id, occurred_at_ms, channel and columns()
     * @return list<EventViewInterface>
     * @throws \InvalidArgumentException When a row has no id or time
     */
    public function assemble(array $rows): array
    {
        $channelsById = [];
        $subjectLabelsById = [];
        $correlationIds = [];
        foreach ($rows as $row) {
            $activityId = $this->id($row);
            $channelsById[$activityId] = $this->text($row, 'channel') ?? '';
            $subjectLabel = $this->text($row, 'subject_label');
            if ($subjectLabel !== null && $subjectLabel !== '') {
                $subjectLabelsById[$activityId] = $subjectLabel;
            }
            $correlationIds[] = $this->text($row, 'correlation_id') ?? '';
        }
        $perOperation = $this->siblingCounter->activitiesPerOperation($correlationIds);
        $details = $this->eventDetails->forActivities($channelsById, $subjectLabelsById);

        $views = [];
        foreach ($rows as $row) {
            $activityId = $this->id($row);
            $correlationId = $this->text($row, 'correlation_id') ?? '';
            $views[] = $this->eventViewFactory->create()
                ->withActivity(
                    $activityId,
                    $this->number($row, 'occurred_at_ms'),
                    $channelsById[$activityId],
                    $correlationId
                )
                ->withActor(
                    ActorType::tryFrom($this->text($row, 'actor_type') ?? '') ?? ActorType::Anonymous,
                    $this->text($row, 'actor_name') ?? '',
                    $this->text($row, 'via_client'),
                    $this->text($row, 'via_version'),
                    $this->text($row, 'via_channel')
                )
                ->withAction(
                    $this->text($row, 'action') ?? '',
                    Outcome::tryFrom($this->text($row, 'outcome') ?? '') ?? Outcome::Failure,
                    $this->text($row, 'message'),
                    is_numeric($row['duration_ms'] ?? null) ? (float) $row['duration_ms'] : null
                )
                ->withSubject(
                    $this->text($row, 'subject_type'),
                    $this->text($row, 'subject_id'),
                    $this->text($row, 'subject_label')
                )
                ->withIp($this->text($row, 'ip'))
                ->withSiblingCount(max(0, ($perOperation[$correlationId] ?? 1) - 1))
                ->withDetail($details[$activityId] ?? null)
                ->withDetailUrl($this->backendUrl->getUrl(self::DETAIL_ROUTE, ['id' => $activityId]));
        }

        return $views;
    }

    /**
     * The row's activity id
     *
     * @param array<string,mixed> $row
     * @return int
     * @throws \InvalidArgumentException When it has none
     */
    private function id(array $row): int
    {
        $id = $this->number($row, 'entity_id');
        if ($id < 1) {
            throw new \InvalidArgumentException('A journal row of a result page has no activity id.');
        }

        return $id;
    }

    /**
     * A whole-number column
     *
     * @param array<string,mixed> $row
     * @param string $column
     * @return int
     * @throws \InvalidArgumentException When it is not a number
     */
    private function number(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(sprintf('A journal row of a result page has no %s.', $column));
        }

        return (int) $value;
    }

    /**
     * A non-empty text column, or null
     *
     * @param array<string,mixed> $row
     * @param string $column
     * @return string|null
     */
    private function text(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
