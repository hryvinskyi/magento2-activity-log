<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data\Search;

use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Api\Data\Outcome;

/**
 * One activity as a search result shows it: the journal's facts, how many other activities its request did, the
 * recording module's details when it has any, and where its detail page is
 *
 * Times are ms since the epoch (UTC); the browser formats them in the store's timezone. Create one through
 * EventViewInterfaceFactory and fill it with the with…() methods, each of which returns a copy.
 *
 * @api
 */
interface EventViewInterface
{
    /**
     * Id of the activity in the journal
     *
     * @return int
     */
    public function getActivityId(): int;

    /**
     * When it happened, ms since the epoch (UTC); the browser formats it in the store's timezone
     *
     * @return int
     */
    public function getOccurredAtMs(): int;

    /**
     * Code of the channel that recorded it
     *
     * @return string
     */
    public function getChannel(): string;

    /**
     * Id of the operation (request, command, job) it belongs to
     *
     * @return string
     */
    public function getCorrelationId(): string;

    /**
     * Kind of actor who did it
     *
     * @return ActorType
     */
    public function getActorType(): ActorType;

    /**
     * Name of the actor who did it
     *
     * @return string
     */
    public function getActorName(): string;

    /**
     * Client the actor worked through, or null
     *
     * @return string|null
     */
    public function getViaClient(): ?string;

    /**
     * Version of that client, or null
     *
     * @return string|null
     */
    public function getViaVersion(): ?string;

    /**
     * Code of the channel that client acted through, or null; it may name a channel no installed module registers
     *
     * @return string|null
     */
    public function getViaChannel(): ?string;

    /**
     * The action as recorded
     *
     * @return string
     */
    public function getAction(): string;

    /**
     * How it ended
     *
     * @return Outcome
     */
    public function getOutcome(): Outcome;

    /**
     * The outcome's message, or null
     *
     * @return string|null
     */
    public function getMessage(): ?string;

    /**
     * How long it took in ms, or null when not measured
     *
     * @return float|null
     */
    public function getDurationMs(): ?float;

    /**
     * Address it came from, or null
     *
     * @return string|null
     */
    public function getIp(): ?string;

    /**
     * Type code of the subject it acted on, or null when it had none
     *
     * @return string|null
     */
    public function getSubjectType(): ?string;

    /**
     * Id of the subject, or null
     *
     * @return string|null
     */
    public function getSubjectId(): ?string;

    /**
     * Label of the subject as recorded, or null
     *
     * @return string|null
     */
    public function getSubjectLabel(): ?string;

    /**
     * Other activities of the same operation, in or out of the searched range
     *
     * @return int
     */
    public function getSiblingCount(): int;

    /**
     * What the recording module says about it, or null when the journal is all there is
     *
     * @return EventDetailInterface|null
     */
    public function getDetail(): ?EventDetailInterface;

    /**
     * The verb of its sentence: the details' verb, else "performed"
     *
     * @return Verb
     */
    public function getVerb(): Verb;

    /**
     * Admin URL of its detail page, or '' when not given
     *
     * @return string
     */
    public function getDetailUrl(): string;

    /**
     * A copy identifying the activity
     *
     * @param int $activityId One or more
     * @param int $occurredAtMs Zero or more
     * @param string $channel
     * @param string $correlationId
     * @return EventViewInterface
     * @throws \InvalidArgumentException When a number is out of range
     */
    public function withActivity(
        int $activityId,
        int $occurredAtMs,
        string $channel,
        string $correlationId
    ): EventViewInterface;

    /**
     * A copy with the actor
     *
     * @param ActorType $type
     * @param string $name
     * @param string|null $viaClient
     * @param string|null $viaVersion
     * @param string|null $viaChannel
     * @return EventViewInterface
     */
    public function withActor(
        ActorType $type,
        string $name,
        ?string $viaClient,
        ?string $viaVersion,
        ?string $viaChannel
    ): EventViewInterface;

    /**
     * A copy with the action and how it ended
     *
     * @param string $action
     * @param Outcome $outcome
     * @param string|null $message
     * @param float|null $durationMs
     * @return EventViewInterface
     */
    public function withAction(
        string $action,
        Outcome $outcome,
        ?string $message,
        ?float $durationMs
    ): EventViewInterface;

    /**
     * A copy with the subject; a null type means there is none
     *
     * @param string|null $type
     * @param string|null $id
     * @param string|null $label
     * @return EventViewInterface
     */
    public function withSubject(?string $type, ?string $id, ?string $label): EventViewInterface;

    /**
     * A copy with the address it came from
     *
     * @param string|null $ip
     * @return EventViewInterface
     */
    public function withIp(?string $ip): EventViewInterface;

    /**
     * A copy with the number of other activities of its operation
     *
     * @param int $siblingCount Zero or more
     * @return EventViewInterface
     * @throws \InvalidArgumentException When the count is negative
     */
    public function withSiblingCount(int $siblingCount): EventViewInterface;

    /**
     * A copy with the recording module's details, or without any
     *
     * @param EventDetailInterface|null $detail
     * @return EventViewInterface
     */
    public function withDetail(?EventDetailInterface $detail): EventViewInterface;

    /**
     * A copy with the URL of its detail page
     *
     * @param string $url An http(s) URL or a path from the site root
     * @return EventViewInterface
     * @throws \InvalidArgumentException When the URL is of another kind
     */
    public function withDetailUrl(string $url): EventViewInterface;
}
