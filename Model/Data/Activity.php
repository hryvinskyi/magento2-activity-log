<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data;

use Hryvinskyi\ActivityLog\Api\Data\ActivityInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterface;
use Hryvinskyi\ActivityLog\Api\Data\OriginInterface;
use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ActivityLog\Api\Data\SubjectInterface;

/**
 * Immutable journal record, created through ActivityInterfaceFactory
 */
class Activity implements ActivityInterface
{
    public const CHANNEL_PATTERN = '/^[a-z][a-z0-9_]{0,31}$/';
    public const CORRELATION_PATTERN = '/^[A-Za-z0-9._-]{8,64}$/';
    public const ACTION_MAX_LENGTH = 255;

    /**
     * @param string $correlationId
     * @param string $channel
     * @param string $action
     * @param ActorInterface $actor
     * @param Outcome $outcome
     * @param OriginInterface $origin
     * @param int $occurredAtMs
     * @param SubjectInterface|null $subject
     * @param string|null $message
     * @param float|null $durationMs
     * @param int|null $id
     * @throws \InvalidArgumentException When a value breaks the journal's rules
     */
    public function __construct(
        private readonly string $correlationId,
        private readonly string $channel,
        private readonly string $action,
        private readonly ActorInterface $actor,
        private readonly Outcome $outcome,
        private readonly OriginInterface $origin,
        private readonly int $occurredAtMs,
        private readonly ?SubjectInterface $subject = null,
        private readonly ?string $message = null,
        private readonly ?float $durationMs = null,
        private readonly ?int $id = null
    ) {
        if (preg_match(self::CORRELATION_PATTERN, $correlationId) !== 1) {
            throw new \InvalidArgumentException(sprintf('Malformed correlation id "%s".', $correlationId));
        }

        if (preg_match(self::CHANNEL_PATTERN, $channel) !== 1) {
            throw new \InvalidArgumentException(sprintf('Malformed channel code "%s".', $channel));
        }

        if (trim($action) === '' || mb_strlen($action) > self::ACTION_MAX_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('An action is 1 to %d characters long.', self::ACTION_MAX_LENGTH)
            );
        }

        if ($occurredAtMs <= 0) {
            throw new \InvalidArgumentException('The time of an activity is a positive number of milliseconds.');
        }

        if ($durationMs !== null && $durationMs < 0) {
            throw new \InvalidArgumentException('A duration cannot be negative.');
        }

        if ($id !== null && $id <= 0) {
            throw new \InvalidArgumentException('A journal id is a positive integer.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getId(): ?int
    {
        return $this->id;
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
    public function getChannel(): string
    {
        return $this->channel;
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
    public function getSubject(): ?SubjectInterface
    {
        return $this->subject;
    }

    /**
     * @inheritDoc
     */
    public function getActor(): ActorInterface
    {
        return $this->actor;
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
    public function getOrigin(): OriginInterface
    {
        return $this->origin;
    }

    /**
     * @inheritDoc
     */
    public function getOccurredAtMs(): int
    {
        return $this->occurredAtMs;
    }
}
