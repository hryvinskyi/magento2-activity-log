<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Actor;

use Hryvinskyi\ActivityLog\Api\Data\ActorInterface;
use Hryvinskyi\ActivityLog\Api\UserActorsInterface;
use Magento\AsynchronousOperations\Api\Data\BulkSummaryInterface;
use Magento\AsynchronousOperations\Api\Data\BulkSummaryInterfaceFactory;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * The account that scheduled a bulk action, as an actor
 *
 * Magento stores the owner with the bulk. A consumer works through a bulk's operations one by one, so the owner of
 * each bulk is read once and kept for the process; a few bulks are kept, the oldest forgotten first.
 */
class BulkOwners implements ResetAfterRequestInterface
{
    private const KEPT_BULKS = 20;

    /**
     * @var array<string, ActorInterface|null>
     */
    private array $owners = [];

    /**
     * @param EntityManager $entityManager
     * @param BulkSummaryInterfaceFactory $bulkFactory
     * @param UserActorsInterface $userActors
     */
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly BulkSummaryInterfaceFactory $bulkFactory,
        private readonly UserActorsInterface $userActors
    ) {
    }

    /**
     * The owner of a bulk, or null when the bulk is gone or no account owns it
     *
     * @param string $bulkUuid
     * @return ActorInterface|null
     */
    public function ownerOf(string $bulkUuid): ?ActorInterface
    {
        if (array_key_exists($bulkUuid, $this->owners)) {
            return $this->owners[$bulkUuid];
        }

        $bulk = $this->bulkFactory->create();
        $this->entityManager->load($bulk, $bulkUuid);
        $owner = $this->actorOf($bulk);

        if (count($this->owners) >= self::KEPT_BULKS) {
            array_shift($this->owners);
        }

        return $this->owners[$bulkUuid] = $owner;
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->owners = [];
    }

    /**
     * The actor of a loaded bulk's owner
     *
     * @param BulkSummaryInterface $bulk
     * @return ActorInterface|null
     */
    private function actorOf(BulkSummaryInterface $bulk): ?ActorInterface
    {
        return $this->userActors->actorOf((int) $bulk->getUserType(), (int) $bulk->getUserId());
    }
}
