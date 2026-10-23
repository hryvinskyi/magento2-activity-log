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
use Hryvinskyi\ActivityLog\Model\Actor\Resolver\CallingAgentActorResolver;
use Hryvinskyi\ActivityLog\Model\Data\Actor;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A program calling without signing in is named by its user agent; a browser is not
 */
class CallingAgentActorResolverTest extends TestCase
{
    /**
     * @return array<string, array{string|false, string|null}>
     */
    public static function userAgents(): array
    {
        return [
            'a webhook' => ['acme-webhook-invoker/1.2384', 'acme-webhook-invoker/1.2384'],
            'a client with details after its product' => ['curl/8.4.0 (x86_64)', 'curl/8.4.0'],
            'a browser' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:158.0) Gecko/20100101 Firefox/158.0', null],
            'an old browser' => ['Opera/9.80 (Windows NT 6.1)', null],
            'no user agent' => [false, null],
            'an empty one' => ['   ', null],
            'a very long one' => [str_repeat('x', 100), str_repeat('x', 64)],
        ];
    }

    #[DataProvider('userAgents')]
    public function testAProgramIsNamedByItsUserAgentAndStaysAnonymous(string|false $userAgent, ?string $name): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getHeader')->willReturn($userAgent);

        $actor = $this->resolver($request)->resolve();

        self::assertSame(
            $name === null ? [null, null] : [ActorType::Anonymous, $name],
            [$actor?->getType(), $actor?->getName()]
        );
    }

    public function testARequestThatIsNotHttpHasNoAgent(): void
    {
        self::assertNull($this->resolver($this->createStub(RequestInterface::class))->resolve());
    }

    /**
     * @param RequestInterface $request
     * @return CallingAgentActorResolver
     */
    private function resolver(RequestInterface $request): CallingAgentActorResolver
    {
        $factory = $this->createStub(ActorInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(
            static function (array $data): ActorInterface {
                self::assertInstanceOf(ActorType::class, $data['type']);
                self::assertIsString($data['name']);

                return new Actor($data['type'], $data['name']);
            }
        );

        return new CallingAgentActorResolver($request, $factory, ['Mozilla/', 'Opera/']);
    }
}
