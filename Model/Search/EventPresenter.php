<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\EventDetailInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\EventViewInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\FieldChangeInterface;

/**
 * One search result as the JSON the timeline renders
 *
 * Codes (verb, kind, outcome, actor type) and data only, never words or markup: the page holds the wording and
 * renders text as text. "detail" is null when only the journal describes the activity.
 */
class EventPresenter
{
    /**
     * The result as plain data
     *
     * @param EventViewInterface $event
     * @return array<string, mixed>
     */
    public function present(EventViewInterface $event): array
    {
        $detail = $event->getDetail();
        $subjectType = $event->getSubjectType();

        return [
            'id' => $event->getActivityId(),
            'occurredAtMs' => $event->getOccurredAtMs(),
            'channel' => $event->getChannel(),
            'correlationId' => $event->getCorrelationId(),
            'siblingCount' => $event->getSiblingCount(),
            'actor' => [
                'type' => $event->getActorType()->value,
                'name' => $event->getActorName(),
                'viaClient' => $event->getViaClient(),
                'viaVersion' => $event->getViaVersion(),
                'viaChannel' => $event->getViaChannel(),
            ],
            'action' => $event->getAction(),
            'outcome' => $event->getOutcome()->value,
            'message' => $event->getMessage(),
            'durationMs' => $event->getDurationMs(),
            'ip' => $event->getIp(),
            'subject' => $subjectType === null ? null : [
                'type' => $subjectType,
                'id' => $event->getSubjectId(),
                'label' => $event->getSubjectLabel(),
            ],
            'verb' => $event->getVerb()->value,
            'detail' => $detail === null ? null : $this->detail($detail),
            'detailUrl' => $event->getDetailUrl(),
        ];
    }

    /**
     * The recording module's details
     *
     * @param EventDetailInterface $detail
     * @return array<string, mixed>
     */
    private function detail(EventDetailInterface $detail): array
    {
        return [
            'what' => $detail->getWhat(),
            'fieldCount' => $detail->getFieldCount(),
            'automaticCount' => $detail->getAutomaticCount(),
            'subjectTypeLabel' => $detail->getSubjectTypeLabel(),
            'subjectLabel' => $detail->getSubjectLabel(),
            'subjectUrl' => $detail->getSubjectUrl(),
            'fields' => array_map(
                fn (FieldChangeInterface $field): array => $this->field($field),
                $detail->getFields()
            ),
            'moreFieldCount' => $detail->getMoreFieldCount(),
            'unchangedCount' => $detail->getUnchangedCount(),
            'unchangedRecorded' => $detail->areUnchangedFieldsRecorded(),
        ];
    }

    /**
     * One field row
     *
     * @param FieldChangeInterface $field
     * @return array<string, mixed>
     */
    private function field(FieldChangeInterface $field): array
    {
        return [
            'name' => $field->getName(),
            'label' => $field->getLabel(),
            'scope' => $field->getScope(),
            'before' => $field->getBefore(),
            'after' => $field->getAfter(),
            'kind' => $field->getKind()->value,
            'changed' => $field->isChanged(),
            'automatic' => $field->isAutomatic(),
            'truncated' => $field->isTruncated(),
            'beforeLength' => $field->getBeforeLength(),
            'afterLength' => $field->getAfterLength(),
        ];
    }
}
