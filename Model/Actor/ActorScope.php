<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Actor;

use Hryvinskyi\ActivityLog\Api\ActorResolverInterface;
use Hryvinskyi\ActivityLog\Api\ActorScopeInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * The actors work is being done for, innermost last; as a resolver it names the innermost one while any work runs
 *
 * Registered first among the actor context's resolvers: work done on someone's behalf is theirs, whatever session or
 * process it runs in.
 */
class ActorScope implements ActorScopeInterface, ActorResolverInterface, ResetAfterRequestInterface
{
    /**
     * @var list<ActorInterface>
     */
    private array $actors = [];

    /**
     * @inheritDoc
     */
    public function actAs(ActorInterface $actor, callable $work): mixed
    {
        $this->actors[] = $actor;
        $depth = count($this->actors);
        try {
            return $work();
        } finally {
            $this->actors = array_slice($this->actors, 0, $depth - 1);
        }
    }

    /**
     * @inheritDoc
     */
    public function resolve(): ?ActorInterface
    {
        return $this->actors === [] ? null : $this->actors[count($this->actors) - 1];
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->actors = [];
    }
}
