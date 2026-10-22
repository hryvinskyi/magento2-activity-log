<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data\Search;

use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ActivityLog\Api\Data\Search\EventDetailInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\EventViewInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\Verb;

/**
 * Immutable search result, created empty through EventViewInterfaceFactory and filled through its with…() methods
 */
class EventView implements EventViewInterface
{
    /**
     * @var int
     */
    private int $activityId = 0;

    /**
     * @var int
     */
    private int $occurredAtMs = 0;

    /**
     * @var string
     */
    private string $channel = '';

    /**
     * @var string
     */
    private string $correlationId = '';

    /**
     * @var ActorType
     */
    private ActorType $actorType = ActorType::Anonymous;

    /**
     * @var string
     */
    private string $actorName = '';

    /**
     * @var string|null
     */
    private ?string $viaClient = null;

    /**
     * @var string|null
     */
    private ?string $viaVersion = null;

    /**
     * @var string|null
     */
    private ?string $viaChannel = null;

    /**
     * @var string
     */
    private string $action = '';

    /**
     * @var Outcome
     */
    private Outcome $outcome = Outcome::Success;

    /**
     * @var string|null
     */
    private ?string $message = null;

    /**
     * @var float|null
     */
    private ?float $durationMs = null;

    /**
     * @var string|null
     */
    private ?string $ip = null;

    /**
     * @var string|null
     */
    private ?string $subjectType = null;

    /**
     * @var string|null
     */
    private ?string $subjectId = null;

    /**
     * @var string|null
     */
    private ?string $subjectLabel = null;

    /**
     * @var int
     */
    private int $siblingCount = 0;

    /**
     * @var EventDetailInterface|null
     */
    private ?EventDetailInterface $detail = null;

    /**
     * @var string
     */
    private string $detailUrl = '';

    /**
     * @param LinkPolicy $linkPolicy
     */
    public function __construct(
        private readonly LinkPolicy $linkPolicy
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getActivityId(): int
    {
        return $this->activityId;
    }

    /**
     * @inheritDoc
     */
    public function getOccurredAtMs(): int
    {
        return $this->occurredAtMs;
    }

    /**
     * @inheritDoc
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * @inheritDoc
     */
    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    /**
     * @inheritDoc
     */
    public function getActorType(): ActorType
    {
        return $this->actorType;
    }

    /**
     * @inheritDoc
     */
    public function getActorName(): string
    {
        return $this->actorName;
    }

    /**
     * @inheritDoc
     */
    public function getViaClient(): ?string
    {
        return $this->viaClient;
    }

    /**
     * @inheritDoc
     */
    public function getViaVersion(): ?string
    {
        return $this->viaVersion;
    }

    /**
     * @inheritDoc
     */
    public function getViaChannel(): ?string
    {
        return $this->viaChannel;
    }

    /**
     * @inheritDoc
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @inheritDoc
     */
    public function getOutcome(): Outcome
    {
        return $this->outcome;
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @inheritDoc
     */
    public function getDurationMs(): ?float
    {
        return $this->durationMs;
    }

    /**
     * @inheritDoc
     */
    public function getIp(): ?string
    {
        return $this->ip;
    }

    /**
     * @inheritDoc
     */
    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    /**
     * @inheritDoc
     */
    public function getSubjectId(): ?string
    {
        return $this->subjectId;
    }

    /**
     * @inheritDoc
     */
    public function getSubjectLabel(): ?string
    {
        return $this->subjectLabel;
    }

    /**
     * @inheritDoc
     */
    public function getSiblingCount(): int
    {
        return $this->siblingCount;
    }

    /**
     * @inheritDoc
     */
    public function getDetail(): ?EventDetailInterface
    {
        return $this->detail;
    }

    /**
     * @inheritDoc
     */
    public function getVerb(): Verb
    {
        return $this->detail?->getVerb() ?? Verb::Performed;
    }

    /**
     * @inheritDoc
     */
    public function getDetailUrl(): string
    {
        return $this->detailUrl;
    }

    /**
     * @inheritDoc
     */
    public function withActivity(
        int $activityId,
        int $occurredAtMs,
        string $channel,
        string $correlationId
    ): EventViewInterface {
        if ($activityId < 1 || $occurredAtMs < 0) {
            throw new \InvalidArgumentException(
                sprintf(
                    'An activity has an id of 1 or more and a time of 0 or more, not (%d, %d).',
                    $activityId,
                    $occurredAtMs
                )
            );
        }

        $copy = clone $this;
        $copy->activityId = $activityId;
        $copy->occurredAtMs = $occurredAtMs;
        $copy->channel = $channel;
        $copy->correlationId = $correlationId;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withActor(
        ActorType $type,
        string $name,
        ?string $viaClient,
        ?string $viaVersion,
        ?string $viaChannel
    ): EventViewInterface {
        $copy = clone $this;
        $copy->actorType = $type;
        $copy->actorName = $name;
        $copy->viaClient = $viaClient;
        $copy->viaVersion = $viaVersion;
        $copy->viaChannel = $viaChannel;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withAction(
        string $action,
        Outcome $outcome,
        ?string $message,
        ?float $durationMs
    ): EventViewInterface {
        $copy = clone $this;
        $copy->action = $action;
        $copy->outcome = $outcome;
        $copy->message = $message;
        $copy->durationMs = $durationMs;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withSubject(?string $type, ?string $id, ?string $label): EventViewInterface
    {
        $copy = clone $this;
        $copy->subjectType = $type;
        $copy->subjectId = $type === null ? null : $id;
        $copy->subjectLabel = $type === null ? null : $label;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withIp(?string $ip): EventViewInterface
    {
        $copy = clone $this;
        $copy->ip = $ip;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withSiblingCount(int $siblingCount): EventViewInterface
    {
        if ($siblingCount < 0) {
            throw new \InvalidArgumentException(
                sprintf('A number of other activities is 0 or more, not %d.', $siblingCount)
            );
        }

        $copy = clone $this;
        $copy->siblingCount = $siblingCount;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withDetail(?EventDetailInterface $detail): EventViewInterface
    {
        $copy = clone $this;
        $copy->detail = $detail;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withDetailUrl(string $url): EventViewInterface
    {
        $this->linkPolicy->assertSafe($url);

        $copy = clone $this;
        $copy->detailUrl = $url;

        return $copy;
    }
}
