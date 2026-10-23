<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Model\Actor;

use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Hryvinskyi\ActivityLog\Model\Actor\ActorContext;
use Hryvinskyi\ActivityLog\Model\Actor\Resolver\ProcessActorResolver;
use Laminas\Http\Headers;
use Magento\Framework\App\RequestInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\Request;
use PHPUnit\Framework\TestCase;

/**
 * The actor of a web request nobody signed in to, with the container's resolvers: a program is named by its user
 * agent, a browser stays anonymous
 *
 * The tests run from the command line, so the process resolver is told it serves a web request; every other resolver
 * is the pool's own.
 */
class CallingAgentTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->headers()->clearHeaders();
        $objectManager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objectManager);
        $objectManager->configure([ProcessActorResolver::class => ['arguments' => ['sapi' => null]]]);
        $objectManager->removeSharedInstance(ProcessActorResolver::class);
    }

    public function testAWebhookIsNamedByItsUserAgent(): void
    {
        $this->headers()->addHeaderLine('User-Agent', 'acme-webhook-invoker/1.2384');

        $actor = $this->webContext()->current();

        self::assertSame(
            [ActorType::Anonymous, 'acme-webhook-invoker/1.2384'],
            [$actor->getType(), $actor->getName()]
        );
    }

    public function testABrowserNobodySignedInToStaysAnonymous(): void
    {
        $this->headers()->addHeaderLine(
            'User-Agent',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:158.0) Gecko/20100101 Firefox/158.0'
        );

        $actor = $this->webContext()->current();

        self::assertSame([ActorType::Anonymous, 'anonymous'], [$actor->getType(), $actor->getName()]);
    }

    /**
     * The container's actor context, with every resolver of its pool, as a web request sees it
     *
     * @return ActorContext
     */
    private function webContext(): ActorContext
    {
        $objectManager = Bootstrap::getObjectManager();
        self::assertInstanceOf(ObjectManager::class, $objectManager);
        $objectManager->configure([ProcessActorResolver::class => ['arguments' => ['sapi' => 'fpm-fcgi']]]);
        $objectManager->removeSharedInstance(ProcessActorResolver::class);
        $context = $objectManager->create(ActorContext::class);
        self::assertInstanceOf(ActorContext::class, $context);

        return $context;
    }

    /**
     * The headers of the request the container serves
     *
     * @return Headers
     */
    private function headers(): Headers
    {
        $request = Bootstrap::getObjectManager()->get(RequestInterface::class);
        self::assertInstanceOf(Request::class, $request);
        $headers = $request->getHeaders();
        self::assertInstanceOf(Headers::class, $headers);

        return $headers;
    }
}
