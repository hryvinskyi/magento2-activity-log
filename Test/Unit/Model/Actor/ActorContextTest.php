<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Unit\Model\Actor;

use Hryvinskyi\ActivityLog\Api\ActorResolverInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Model\Actor\ActorContext;
use Hryvinskyi\ActivityLog\Model\Data\Actor;
use PHPUnit\Framework\TestCase;

class ActorContextTest extends TestCase
{
    public function testTakesTheFirstResolverThatAnswers(): void
    {
        $delegated = new Actor(ActorType::Admin, 'jane.doe', '7', 'Desk Assistant', '1.4.2');
        $session = new Actor(ActorType::Admin, 'jane.doe', '7');

        $context = new ActorContext($this->factory(), [
            'silent' => $this->resolver(null),
            'delegation' => $this->resolver($delegated),
            'session' => $this->resolver($session),
        ]);

        self::assertSame($delegated, $context->current());
    }

    public function testIsAnonymousWhenNobodyAnswers(): void
    {
        $context = new ActorContext($this->factory(), ['silent' => $this->resolver(null)]);

        $actor = $context->current();

        self::assertSame(ActorType::Anonymous, $actor->getType());
        self::assertSame('anonymous', $actor->getName());
    }

    public function testAsksAgainEveryTime(): void
    {
        $first = new Actor(ActorType::Admin, 'jane.doe', '7', 'Desk Assistant');
        $second = new Actor(ActorType::Admin, 'jane.doe', '7');
        $resolver = $this->createStub(ActorResolverInterface::class);
        $resolver->method('resolve')->willReturnOnConsecutiveCalls($first, $second);

        $context = new ActorContext($this->factory(), ['changing' => $resolver]);

        self::assertSame($first, $context->current());
        self::assertSame($second, $context->current());
    }

    /**
     * @param ActorInterface|null $actor
     * @return ActorResolverInterface
     */
    private function resolver(?ActorInterface $actor): ActorResolverInterface
    {
        $resolver = $this->createStub(ActorResolverInterface::class);
        $resolver->method('resolve')->willReturn($actor);

        return $resolver;
    }

    /**
     * Stands in for the generated factory
     *
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
