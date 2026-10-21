<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\ResourceModel;

use Hryvinskyi\ActivityLog\Api\Data\ActivityInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActivityInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Api\Data\OriginInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ActivityLog\Api\Data\SubjectInterfaceFactory;

/**
 * Translates between an activity and a journal row
 */
class ActivityRowMapper
{
    /**
     * @param ActivityInterfaceFactory $activityFactory
     * @param ActorInterfaceFactory $actorFactory
     * @param SubjectInterfaceFactory $subjectFactory
     * @param OriginInterfaceFactory $originFactory
     */
    public function __construct(
        private readonly ActivityInterfaceFactory $activityFactory,
        private readonly ActorInterfaceFactory $actorFactory,
        private readonly SubjectInterfaceFactory $subjectFactory,
        private readonly OriginInterfaceFactory $originFactory
    ) {
    }

    /**
     * Row to insert for an activity
     *
     * @param ActivityInterface $activity
     * @return array<string, string|int|float|null>
     */
    public function toRow(ActivityInterface $activity): array
    {
        $subject = $activity->getSubject();
        $actor = $activity->getActor();
        $origin = $activity->getOrigin();

        return [
            'correlation_id' => $activity->getCorrelationId(),
            'channel' => $activity->getChannel(),
            'action' => $activity->getAction(),
            'subject_type' => $subject?->getType(),
            'subject_id' => $subject?->getId(),
            'subject_label' => $subject?->getLabel(),
            'actor_type' => $actor->getType()->value,
            'actor_id' => $actor->getId(),
            'actor_name' => $actor->getName(),
            'via_client' => $actor->getViaClient(),
            'via_version' => $actor->getViaVersion(),
            'via_channel' => $actor->getViaChannel(),
            'outcome' => $activity->getOutcome()->value,
            'message' => $activity->getMessage(),
            'duration_ms' => $activity->getDurationMs(),
            'store_id' => $origin->getStoreId(),
            'ip' => $origin->getIp(),
            'user_agent' => $origin->getUserAgent(),
            'occurred_at_ms' => $activity->getOccurredAtMs(),
            'created_at' => gmdate('Y-m-d H:i:s', intdiv($activity->getOccurredAtMs(), 1000)),
        ];
    }

    /**
     * Activity read back from a row
     *
     * @param array<mixed> $row
     * @return ActivityInterface
     * @throws \InvalidArgumentException When the row no longer satisfies the journal's rules
     */
    public function fromRow(array $row): ActivityInterface
    {
        $subjectType = $this->text($row, 'subject_type');

        return $this->activityFactory->create([
            'id' => (int) $this->scalar($row, 'entity_id'),
            'correlationId' => (string) $this->text($row, 'correlation_id'),
            'channel' => (string) $this->text($row, 'channel'),
            'action' => (string) $this->text($row, 'action'),
            'actor' => $this->actorFactory->create([
                'type' => ActorType::tryFrom((string) $this->text($row, 'actor_type')) ?? ActorType::Anonymous,
                'name' => (string) $this->text($row, 'actor_name'),
                'id' => $this->text($row, 'actor_id'),
                'viaClient' => $this->text($row, 'via_client'),
                'viaVersion' => $this->text($row, 'via_version'),
                'viaChannel' => $this->text($row, 'via_channel'),
            ]),
            'outcome' => Outcome::tryFrom((string) $this->text($row, 'outcome')) ?? Outcome::Failure,
            'origin' => $this->originFactory->create([
                'storeId' => $this->scalar($row, 'store_id') !== null ? (int) $this->scalar($row, 'store_id') : null,
                'ip' => $this->text($row, 'ip'),
                'userAgent' => $this->text($row, 'user_agent'),
            ]),
            'occurredAtMs' => (int) $this->scalar($row, 'occurred_at_ms'),
            'subject' => $subjectType === null ? null : $this->subjectFactory->create([
                'type' => $subjectType,
                'label' => (string) $this->text($row, 'subject_label'),
                'id' => $this->text($row, 'subject_id'),
            ]),
            'message' => $this->text($row, 'message'),
            'durationMs' => $this->scalar($row, 'duration_ms') !== null
                ? (float) $this->scalar($row, 'duration_ms')
                : null,
        ]);
    }

    /**
     * A non-empty text column, or null
     *
     * @param array<mixed> $row
     * @param string $column
     * @return string|null
     */
    private function text(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * A numeric column, or null
     *
     * @param array<mixed> $row
     * @param string $column
     * @return int|float|string|null
     */
    private function scalar(array $row, string $column): int|float|string|null
    {
        $value = $row[$column] ?? null;

        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)) ? $value : null;
    }
}
