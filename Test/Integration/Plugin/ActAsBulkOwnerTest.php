<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Plugin;

use Hryvinskyi\ActivityLog\Api\ActorContextInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Plugin\AsynchronousOperations\ActAsBulkOwner;
use Magento\AsynchronousOperations\Api\Data\BulkSummaryInterfaceFactory;
use Magento\AsynchronousOperations\Api\Data\OperationInterfaceFactory;
use Magento\AsynchronousOperations\Model\ConfigInterface as AsyncConfig;
use Magento\AsynchronousOperations\Model\OperationProcessor;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\MessageQueue\MessageEncoder;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\User\Model\User;
use Magento\User\Model\UserFactory;
use PHPUnit\Framework\TestCase;

/**
 * An operation of a bulk action is carried out for the admin who scheduled the bulk, through the real bulk store,
 * message encoder and actor context
 */
#[DbIsolation(true)]
class ActAsBulkOwnerTest extends TestCase
{
    public function testTheOperationActsAsTheAdminWhoScheduledTheBulk(): void
    {
        $admin = $this->admin();
        $adminId = $admin->getId();
        self::assertIsNumeric($adminId);
        $message = $this->operationOf($this->bulkOf(UserContextInterface::USER_TYPE_ADMIN, (int) $adminId));

        $actor = $this->plugin()->aroundProcess(
            $this->createStub(OperationProcessor::class),
            static fn (): mixed => self::service(ActorContextInterface::class)->current(),
            $message
        );

        self::assertInstanceOf(\Hryvinskyi\ActivityLog\Api\Data\ActorInterface::class, $actor);
        self::assertSame(
            [ActorType::Admin, $admin->getUserName(), (string) $adminId],
            [$actor->getType(), $actor->getName(), $actor->getId()]
        );
        self::assertNotSame(
            ActorType::Admin,
            self::service(ActorContextInterface::class)->current()->getType(),
            'Once the operation ends, the process acts for itself again'
        );
    }

    public function testAnOperationOfABulkNobodyOwnsRunsAsTheProcess(): void
    {
        $before = self::service(ActorContextInterface::class)->current()->getType();

        $type = $this->plugin()->aroundProcess(
            $this->createStub(OperationProcessor::class),
            static fn (): mixed => self::service(ActorContextInterface::class)->current()->getType(),
            $this->operationOf('5c1bd8f0-0000-4000-8000-000000000000')
        );

        self::assertSame($before, $type);
    }

    /**
     * The admin the test framework signs in with
     *
     * @return User
     */
    private function admin(): User
    {
        $user = self::service(UserFactory::class)->create();
        $user->loadByUsername(\Magento\TestFramework\Bootstrap::ADMIN_NAME);
        self::assertNotNull($user->getId());

        return $user;
    }

    /**
     * A stored bulk owned by an account
     *
     * @param int $userType
     * @param int $userId
     * @return string The bulk's uuid
     */
    private function bulkOf(int $userType, int $userId): string
    {
        $uuid = sprintf('5c1bd8f0-%04x-4000-8000-%012x', random_int(0, 0xffff), random_int(0, 0xffffffffffff));
        $bulk = self::service(BulkSummaryInterfaceFactory::class)->create();
        $bulk->setBulkId($uuid);
        $bulk->setDescription('Assign sources');
        $bulk->setUserId($userId);
        $bulk->setUserType($userType);
        $bulk->setOperationCount(1);
        self::service(EntityManager::class)->save($bulk);

        return $uuid;
    }

    /**
     * The message a consumer receives for one operation of the bulk
     *
     * @param string $bulkUuid
     * @return string
     */
    private function operationOf(string $bulkUuid): string
    {
        $operation = self::service(OperationInterfaceFactory::class)->create(['data' => [
            'bulk_uuid' => $bulkUuid,
            'topic_name' => 'async.V1.inventory.bulk-product-source-assign.POST',
            'serialized_data' => '{"entity_id":null,"entity_link":"","meta_information":"{}"}',
            'status' => 4,
            'operation_key' => 0,
        ]]);

        return self::service(MessageEncoder::class)->encode(AsyncConfig::SYSTEM_TOPIC_NAME, $operation);
    }

    /**
     * @return ActAsBulkOwner
     */
    private function plugin(): ActAsBulkOwner
    {
        return self::service(ActAsBulkOwner::class);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private static function service(string $class): object
    {
        $service = Bootstrap::getObjectManager()->get($class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
