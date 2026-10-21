<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model;

use Hryvinskyi\ActivityLog\Api\ActivityBuilderInterface;
use Hryvinskyi\ActivityLog\Api\ActivityRecorderInterface;
use Hryvinskyi\ActivityLog\Api\ActivityRepositoryInterface;
use Hryvinskyi\ActivityLog\Api\CorrelationScopeInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Api\Data\Outcome;
use Hryvinskyi\ActivityLog\Api\Data\SubjectInterfaceFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Records through the real container and reads back from the real table
 */
#[DbIsolation(true)]
class ActivityJournalTest extends TestCase
{
    /**
     * @var ActivityBuilderInterface
     */
    private ActivityBuilderInterface $builder;
    /**
     * @var ActivityRecorderInterface
     */
    private ActivityRecorderInterface $recorder;
    /**
     * @var ActivityRepositoryInterface
     */
    private ActivityRepositoryInterface $repository;
    /**
     * @var CorrelationScopeInterface
     */
    private CorrelationScopeInterface $correlationScope;

    protected function setUp(): void
    {
        $this->builder = self::service(ActivityBuilderInterface::class);
        $this->recorder = self::service(ActivityRecorderInterface::class);
        $this->repository = self::service(ActivityRepositoryInterface::class);
        $this->correlationScope = self::service(CorrelationScopeInterface::class);
    }

    public function testAnActivityReadsBackAsItWasRecorded(): void
    {
        $subject = self::service(SubjectInterfaceFactory::class)->create([
            'type' => 'product',
            'label' => 'Product #1245 · SKU 24-MB01',
            'id' => '1245',
        ]);
        $activity = $this->builder
            ->build('rest', 'POST /V1/products', Outcome::Failure, $subject, 642.25, 'Bad Request');

        $id = $this->recorder->record($activity);

        self::assertIsInt($id);
        $stored = $this->repository->get($id);
        self::assertSame($id, $stored->getId());
        self::assertSame('rest', $stored->getChannel());
        self::assertSame('POST /V1/products', $stored->getAction());
        self::assertSame(Outcome::Failure, $stored->getOutcome());
        self::assertSame('Bad Request', $stored->getMessage());
        self::assertSame(642.25, $stored->getDurationMs());
        $storedSubject = $stored->getSubject();
        self::assertNotNull($storedSubject);
        self::assertSame('product', $storedSubject->getType());
        self::assertSame('1245', $storedSubject->getId());
        self::assertSame($activity->getCorrelationId(), $stored->getCorrelationId());
        self::assertSame($activity->getOccurredAtMs(), $stored->getOccurredAtMs());
        self::assertSame(
            ActorType::Cli,
            $stored->getActor()->getType(),
            'A test run is a shell process nobody logged in to.'
        );
    }

    public function testActivitiesOfOneOperationShareItsCorrelationIdInOrder(): void
    {
        $operation = $this->correlationScope->begin();
        $first = $this->recorder->record(
            $this->builder->build('mcp', 'execute_ability catalog.product_repository.save', Outcome::Success)
        );
        $second = $this->recorder->record($this->builder->build('entity', 'Product saved', Outcome::Success));
        $this->correlationScope->end($operation);
        $outside = $this->recorder->record($this->builder->build('rest', 'GET /V1/orders', Outcome::Success));

        $ids = array_map(
            static fn ($activity): ?int => $activity->getId(),
            $this->repository->getByCorrelationId($operation)
        );

        self::assertSame([$first, $second], $ids);
        self::assertNotContains($outside, $ids);
    }

    public function testDeletingAnActivityKeepsTheRestOfItsOperation(): void
    {
        $operation = $this->correlationScope->begin();
        $kept = $this->recorder->record(
            $this->builder->build('mcp', 'execute_ability cms.block_repository.save', Outcome::Success)
        );
        $deleted = $this->recorder->record($this->builder->build('entity', 'Block saved', Outcome::Success));
        $this->correlationScope->end($operation);
        self::assertIsInt($kept);
        self::assertIsInt($deleted);

        self::assertSame(1, $this->repository->deleteByIds([$deleted]));

        self::assertSame($kept, $this->repository->get($kept)->getId());
        $this->expectException(NoSuchEntityException::class);
        $this->repository->get($deleted);
    }

    #[Config('hryvinskyi_activity_log/general/enabled', '0')]
    public function testNothingIsRecordedWhileTheJournalIsOff(): void
    {
        self::assertNull($this->recorder->record($this->builder->build('rest', 'GET /V1/orders', Outcome::Success)));
    }

    /**
     * A service from the real container, typed
     *
     * @template T of object
     * @param class-string<T> $class
     * @param array<string,mixed> $arguments Build a fresh instance with these arguments instead
     * @return T
     */
    private static function service(string $class, array $arguments = []): object
    {
        $objectManager = Bootstrap::getObjectManager();
        $service = $arguments === [] ? $objectManager->get($class) : $objectManager->create($class, $arguments);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
