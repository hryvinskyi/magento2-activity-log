<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Origin;

use Hryvinskyi\ActivityLog\Api\Data\OriginInterface;
use Hryvinskyi\ActivityLog\Api\Data\OriginInterfaceFactory;
use Hryvinskyi\ActivityLog\Model\Data\Origin;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Store, remote address and user agent of the current request; outside HTTP only the store is known
 */
class RequestOriginResolver implements OriginResolverInterface
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param RemoteAddress $remoteAddress
     * @param RequestInterface $request
     * @param OriginInterfaceFactory $originFactory
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly RemoteAddress $remoteAddress,
        private readonly RequestInterface $request,
        private readonly OriginInterfaceFactory $originFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function current(): OriginInterface
    {
        return $this->originFactory->create([
            'storeId' => $this->storeId(),
            'ip' => $this->ip(),
            'userAgent' => $this->userAgent(),
        ]);
    }

    /**
     * Current store id, or null before the store is resolved
     *
     * @return int|null
     */
    private function storeId(): ?int
    {
        try {
            return (int) $this->storeManager->getStore()->getId();
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    /**
     * Remote address; RemoteAddress answers false outside HTTP
     *
     * @return string|null
     */
    private function ip(): ?string
    {
        $ip = (string) $this->remoteAddress->getRemoteAddress();

        return $ip !== '' ? substr($ip, 0, Origin::IP_MAX_LENGTH) : null;
    }

    /**
     * User agent, cut to what the journal stores
     *
     * @return string|null
     */
    private function userAgent(): ?string
    {
        if (!$this->request instanceof Request) {
            return null;
        }

        $userAgent = (string) $this->request->getHeader('User-Agent');

        return $userAgent !== ''
            ? mb_substr($userAgent, 0, Origin::USER_AGENT_MAX_LENGTH)
            : null;
    }
}
