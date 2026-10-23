<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Actor\Resolver;

use Hryvinskyi\ActivityLog\Api\ActorResolverInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActorInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\ActorType;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\Request;

/**
 * Names a program that calls the store without signing in by its user agent: "acme-webhooks/2.1"
 *
 * Asked after the signed-in user and the process, so it answers only for an HTTP request in which nobody is signed
 * in: a payment provider's webhook, a feed fetcher, a script. The actor stays anonymous: the name is what the caller
 * says it is, never checked. A browser is not a program calling the store and keeps the plain anonymous actor, and so
 * does a request without a user agent.
 */
class CallingAgentActorResolver implements ActorResolverInterface
{
    private const NAME_MAX_LENGTH = 64;

    /**
     * @param RequestInterface $request
     * @param ActorInterfaceFactory $actorFactory
     * @param string[] $browserPrefixes How the user agent of a browser starts
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ActorInterfaceFactory $actorFactory,
        private readonly array $browserPrefixes = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(): ?ActorInterface
    {
        if (!$this->request instanceof Request) {
            return null;
        }

        $product = strtok(trim((string) $this->request->getHeader('User-Agent')), " \t");
        if ($product === false || $this->isBrowser($product)) {
            return null;
        }

        return $this->actorFactory->create([
            'type' => ActorType::Anonymous,
            'name' => mb_substr($product, 0, self::NAME_MAX_LENGTH),
        ]);
    }

    /**
     * Whether the user agent's first product is a browser's
     *
     * @param string $product
     * @return bool
     */
    private function isBrowser(string $product): bool
    {
        foreach ($this->browserPrefixes as $prefix) {
            if (stripos($product, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}
