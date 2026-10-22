<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Actor;

use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Model\Actor\ActorScope;
use Hryvinskyi\ActivityLog\Model\Data\Actor;
use PHPUnit\Framework\TestCase;

class ActorScopeTest extends TestCase
{
    public function testNamesNobodyWhileNoWorkRuns(): void
    {
        self::assertNull((new ActorScope())->resolve());
    }

    public function testTheWorkIsTheActorsAndItsResultIsReturned(): void
    {
        $scope = new ActorScope();
        $jane = new Actor(ActorType::Admin, 'jane', '7');

        $seen = $scope->actAs($jane, static fn (): ?string => $scope->resolve()?->getName());

        self::assertSame('jane', $seen);
        self::assertNull($scope->resolve(), 'The scope ends with the work');
    }

    public function testTheInnermostScopeDecidesAndTheOuterOneComesBack(): void
    {
        $scope = new ActorScope();
        $names = [];

        $erp = new Actor(ActorType::Integration, 'erp', '3');
        $scope->actAs(new Actor(ActorType::Admin, 'jane', '7'), static function () use ($scope, $erp, &$names): void {
            $scope->actAs($erp, static function () use ($scope, &$names): void {
                $names[] = $scope->resolve()?->getName();
            });
            $names[] = $scope->resolve()?->getName();
        });

        self::assertSame(['erp', 'jane'], $names);
    }

    public function testTheScopeEndsWhenTheWorkFails(): void
    {
        $scope = new ActorScope();
        $failure = null;

        try {
            $scope->actAs(new Actor(ActorType::Admin, 'jane', '7'), $this->failingWork(...));
        } catch (\RuntimeException $exception) {
            $failure = $exception;
        }

        self::assertInstanceOf(\RuntimeException::class, $failure, 'The failure reaches the caller');
        self::assertNull($scope->resolve());
    }

    /**
     * Work that fails
     *
     * @return never
     * @throws \RuntimeException
     */
    private function failingWork(): never
    {
        throw new \RuntimeException('Failed');
    }

    public function testANewRequestStartsWithoutScopes(): void
    {
        $scope = new ActorScope();
        $scope->actAs(new Actor(ActorType::Admin, 'jane', '7'), static function () use ($scope): void {
            $scope->_resetState();
        });

        self::assertNull($scope->resolve());
    }
}
