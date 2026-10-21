<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

use Hryvinskyi\ActivityLog\Api\Data\ActivityInterface;
use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ActivityLog\Api\Data\SubjectInterface;

/**
 * Builds an activity attributed to the current actor, operation and origin
 *
 * A channel states what happened; the builder fills in who (actor context), which operation (correlation scope),
 * where (origin) and when (clock).
 *
 * @api
 */
interface ActivityBuilderInterface
{
    /**
     * Build an activity for what just happened in the current context
     *
     * @param string $channel Registered channel code
     * @param string $action What happened, as the channel names it
     * @param Outcome $outcome How it ended
     * @param SubjectInterface|null $subject What it acted on
     * @param float|null $durationMs How long it took, when it has a duration of its own
     * @param string|null $message Short explanation shown with the outcome
     * @param int|null $startedAtMs When it started, for an activity recorded after it finished; now when null
     * @return ActivityInterface
     * @throws \InvalidArgumentException When the channel code or the action is malformed
     */
    public function build(
        string $channel,
        string $action,
        Outcome $outcome,
        ?SubjectInterface $subject = null,
        ?float $durationMs = null,
        ?string $message = null,
        ?int $startedAtMs = null
    ): ActivityInterface;
}
