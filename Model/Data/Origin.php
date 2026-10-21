<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data;

use Hryvinskyi\ActivityLog\Api\Data\OriginInterface;

/**
 * Immutable origin, created through OriginInterfaceFactory
 */
class Origin implements OriginInterface
{
    public const IP_MAX_LENGTH = 45;
    public const USER_AGENT_MAX_LENGTH = 512;

    /**
     * @param int|null $storeId
     * @param string|null $ip
     * @param string|null $userAgent
     * @throws \InvalidArgumentException When a value breaks the journal's rules
     */
    public function __construct(
        private readonly ?int $storeId = null,
        private readonly ?string $ip = null,
        private readonly ?string $userAgent = null
    ) {
        if ($storeId !== null && $storeId < 0) {
            throw new \InvalidArgumentException('A store id cannot be negative.');
        }

        if ($ip !== null && ($ip === '' || strlen($ip) > self::IP_MAX_LENGTH)) {
            throw new \InvalidArgumentException(
                sprintf('An IP address is 1 to %d characters long.', self::IP_MAX_LENGTH)
            );
        }

        if ($userAgent !== null && mb_strlen($userAgent) > self::USER_AGENT_MAX_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('A user agent is at most %d characters long.', self::USER_AGENT_MAX_LENGTH)
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): ?int
    {
        return $this->storeId;
    }

    /**
     * @inheritDoc
     */
    public function getIp(): ?string
    {
        return $this->ip;
    }

    /**
     * @inheritDoc
     */
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }
}
