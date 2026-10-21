<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Actor;

use Hryvinskyi\ActivityLog\Api\ActorContextInterface;
use Hryvinskyi\ActivityLog\Api\ActorResolverInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\ActorType;

/**
 * Asks the registered resolvers in sortOrder and takes the first answer
 *
 * Nothing is cached here: a delegating client can start and stop acting for an admin within one request.
 */
class ActorContext implements ActorContextInterface
{
    /**
     * @param ActorInterfaceFactory $actorFactory
     * @param array<string,ActorResolverInterface> $resolvers
     */
    public function __construct(
        private readonly ActorInterfaceFactory $actorFactory,
        private readonly array $resolvers = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function current(): ActorInterface
    {
        foreach ($this->resolvers as $resolver) {
            $actor = $resolver->resolve();
            if ($actor !== null) {
                return $actor;
            }
        }

        return $this->actorFactory->create(['type' => ActorType::Anonymous, 'name' => 'anonymous']);
    }
}
