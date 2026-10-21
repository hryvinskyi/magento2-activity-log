<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Correlation;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\Request;

/**
 * Reads the X-Correlation-Id request header; outside an HTTP request there is none
 */
class HttpHeaderIncomingId implements IncomingIdInterface
{
    public const HEADER = 'X-Correlation-Id';

    /**
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @inheritDoc
     */
    public function read(): ?string
    {
        if (!$this->request instanceof Request) {
            return null;
        }

        $value = trim((string) $this->request->getHeader(self::HEADER));

        return $value !== '' ? $value : null;
    }
}
