<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model;

use Hryvinskyi\ActivityLog\Api\ActivityBuilderInterface;
use Hryvinskyi\ActivityLog\Api\ActorContextInterface;
use Hryvinskyi\ActivityLog\Api\CorrelationScopeInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActivityInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActivityInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ActivityLog\Api\Data\SubjectInterface;
use Hryvinskyi\ActivityLog\Api\MillisecondClockInterface;
use Hryvinskyi\ActivityLog\Model\Origin\OriginResolverInterface;

/**
 * Fills who, which operation, where and when from the current context
 */
class ActivityBuilder implements ActivityBuilderInterface
{
    /**
     * @param ActivityInterfaceFactory $activityFactory
     * @param ActorContextInterface $actorContext
     * @param CorrelationScopeInterface $correlationScope
     * @param OriginResolverInterface $originResolver
     * @param MillisecondClockInterface $clock
     */
    public function __construct(
        private readonly ActivityInterfaceFactory $activityFactory,
        private readonly ActorContextInterface $actorContext,
        private readonly CorrelationScopeInterface $correlationScope,
        private readonly OriginResolverInterface $originResolver,
        private readonly MillisecondClockInterface $clock
    ) {
    }

    /**
     * @inheritDoc
     */
    public function build(
        string $channel,
        string $action,
        Outcome $outcome,
        ?SubjectInterface $subject = null,
        ?float $durationMs = null,
        ?string $message = null,
        ?int $startedAtMs = null
    ): ActivityInterface {
        return $this->activityFactory->create([
            'correlationId' => $this->correlationScope->current(),
            'channel' => $channel,
            'action' => $action,
            'actor' => $this->actorContext->current(),
            'outcome' => $outcome,
            'origin' => $this->originResolver->current(),
            'occurredAtMs' => $startedAtMs ?? $this->clock->nowMs(),
            'subject' => $subject,
            'message' => $message,
            'durationMs' => $durationMs,
        ]);
    }
}
