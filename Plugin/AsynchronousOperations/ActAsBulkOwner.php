<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Plugin\AsynchronousOperations;

use Hryvinskyi\ActivityLog\Api\ActorScopeInterface;
use Hryvinskyi\ActivityLog\Model\Actor\BulkOwners;
use Magento\AsynchronousOperations\Api\Data\OperationInterface;
use Magento\AsynchronousOperations\Model\ConfigInterface as AsyncConfig;
use Magento\AsynchronousOperations\Model\OperationProcessor;
use Magento\Framework\MessageQueue\MessageEncoder;
use Psr\Log\LoggerInterface;

/**
 * An operation of a bulk action is carried out for the account that scheduled the bulk
 *
 * A consumer runs the operations of bulks that admins and integrations scheduled (an asynchronous web API call, a
 * mass action of the admin panel). Without this, what they record would be the consumer process's ("cli"). An
 * operation whose owner cannot be told runs as it would without this; telling it never fails the operation.
 */
class ActAsBulkOwner
{
    /**
     * @param MessageEncoder $messageEncoder
     * @param BulkOwners $bulkOwners
     * @param ActorScopeInterface $actorScope
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly MessageEncoder $messageEncoder,
        private readonly BulkOwners $bulkOwners,
        private readonly ActorScopeInterface $actorScope,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Run the operation in its bulk owner's scope
     *
     * @param OperationProcessor $subject
     * @param callable $proceed
     * @phpstan-param callable(string): mixed $proceed
     * @param string $encodedMessage
     * @return mixed
     */
    public function aroundProcess(OperationProcessor $subject, callable $proceed, string $encodedMessage): mixed
    {
        try {
            $operation = $this->messageEncoder->decode(AsyncConfig::SYSTEM_TOPIC_NAME, $encodedMessage);
            $bulkUuid = $operation instanceof OperationInterface ? $operation->getBulkUuid() : null;
            $owner = is_string($bulkUuid) && $bulkUuid !== '' ? $this->bulkOwners->ownerOf($bulkUuid) : null;
        } catch (\Throwable $exception) {
            $this->logger->error('The owner of a bulk operation could not be told.', ['exception' => $exception]);
            $owner = null;
        }

        return $owner === null
            ? $proceed($encodedMessage)
            : $this->actorScope->actAs($owner, static fn (): mixed => $proceed($encodedMessage));
    }
}
