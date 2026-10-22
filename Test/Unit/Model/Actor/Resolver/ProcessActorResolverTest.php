<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Actor\Resolver;

use Hryvinskyi\ActivityLog\Api\Data\ActorInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Model\Actor\Resolver\ProcessActorResolver;
use Hryvinskyi\ActivityLog\Model\Data\Actor;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ProcessActorResolverTest extends TestCase
{
    public function testTheCronRunnerIsCron(): void
    {
        $actor = (new ProcessActorResolver($this->state(Area::AREA_CRONTAB), $this->factory(), 'cli'))->resolve();

        self::assertSame([ActorType::Cron, 'cron'], [$actor?->getType(), $actor?->getName()]);
    }

    public function testACommandFromTheShellIsCli(): void
    {
        $actor = (new ProcessActorResolver($this->state(null), $this->factory(), 'cli'))->resolve();

        self::assertSame([ActorType::Cli, 'cli'], [$actor?->getType(), $actor?->getName()]);
    }

    public function testAWebRequestIsNoProcessActor(): void
    {
        self::assertNull(
            (new ProcessActorResolver($this->state(Area::AREA_FRONTEND), $this->factory(), 'fpm-fcgi'))->resolve(),
            'A webhook or a guest request is left to the other resolvers, then recorded as anonymous'
        );
    }

    public function testWithoutAServerApiGivenTheRunningProcessDecides(): void
    {
        $actor = (new ProcessActorResolver($this->state(null), $this->factory()))->resolve();

        self::assertSame(PHP_SAPI === 'cli' ? ActorType::Cli : null, $actor?->getType());
    }

    /**
     * The application state, in this area or before any is set
     *
     * @param string|null $area
     * @return State
     */
    private function state(?string $area): State
    {
        $state = $this->createStub(State::class);
        if ($area === null) {
            $state->method('getAreaCode')->willThrowException(new LocalizedException(__('Area code is not set')));
        } else {
            $state->method('getAreaCode')->willReturn($area);
        }

        return $state;
    }

    /**
     * @return ActorInterfaceFactory
     */
    private function factory(): ActorInterfaceFactory
    {
        $factory = $this->createStub(ActorInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(
            static function (array $data): ActorInterface {
                self::assertInstanceOf(ActorType::class, $data['type']);
                self::assertIsString($data['name']);

                return new Actor($data['type'], $data['name']);
            }
        );

        return $factory;
    }
}
