<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data;

/**
 * One journal record: who did what, through which channel, on what, with what outcome
 *
 * Immutable and valid from creation: an instance with an empty action or a malformed channel code cannot exist.
 * A channel keeps whatever else it knows about the activity (a request body, a field diff) in its own storage,
 * keyed by the activity id; that is the extension point, not this contract.
 *
 * @api
 */
interface ActivityInterface
{
    /**
     * Journal id, or null before the activity is recorded
     *
     * @return int|null
     */
    public function getId(): ?int;

    /**
     * Operation this activity belongs to; every activity one operation caused shares it
     *
     * @return string
     */
    public function getCorrelationId(): string;

    /**
     * Code of the channel that recorded it, e.g. "rest" or "admin"
     *
     * @return string
     */
    public function getChannel(): string;

    /**
     * What happened, as the channel names it: "POST /V1/products", "catalog/product/save", "Product saved"
     *
     * @return string
     */
    public function getAction(): string;

    /**
     * What it acted on, or null when the action has no single subject
     *
     * @return SubjectInterface|null
     */
    public function getSubject(): ?SubjectInterface;

    /**
     * Who it is attributed to
     *
     * @return ActorInterface
     */
    public function getActor(): ActorInterface;

    /**
     * How it ended
     *
     * @return Outcome
     */
    public function getOutcome(): Outcome;

    /**
     * Short explanation shown with the outcome, or null
     *
     * @return string|null
     */
    public function getMessage(): ?string;

    /**
     * Duration in milliseconds, or null when the activity has no duration of its own
     *
     * @return float|null
     */
    public function getDurationMs(): ?float;

    /**
     * Where it came from
     *
     * @return OriginInterface
     */
    public function getOrigin(): OriginInterface;

    /**
     * When it happened, in milliseconds since the Unix epoch (UTC)
     *
     * @return int
     */
    public function getOccurredAtMs(): int;
}
