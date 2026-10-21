<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data;

/**
 * Who an activity is attributed to
 *
 * For a delegated action the actor is the account whose permissions were evaluated, and "via" names the client that
 * issued the call on its behalf (an AI agent's client, for example).
 *
 * @api
 */
interface ActorInterface
{
    /**
     * Kind of account
     *
     * @return ActorType
     */
    public function getType(): ActorType;

    /**
     * Account identifier within its kind, or null when the kind has none (guest, cli, cron, anonymous)
     *
     * @return string|null
     */
    public function getId(): ?string;

    /**
     * Name shown to people: an admin username, an integration name, a customer email, "cron"
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Name of the client that acted on the account's behalf, or null when the account acted itself
     *
     * @return string|null
     */
    public function getViaClient(): ?string;

    /**
     * Version of that client, when it reported one
     *
     * @return string|null
     */
    public function getViaVersion(): ?string;

    /**
     * Code of the channel the client acted through, when the account did not act itself
     *
     * @return string|null
     */
    public function getViaChannel(): ?string;
}
