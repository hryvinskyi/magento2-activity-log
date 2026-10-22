<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCursorInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCursorInterfaceFactory;
use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\Url\EncoderInterface;

/**
 * A page position as an opaque URL-safe token and back
 *
 * The token is "time.id.checksum" through Magento's URL-safe encoding (base64 with "-", "_" and "~"). The checksum
 * is not a secret — a position reveals nothing a reader of the log cannot see — it makes a mangled token fail loudly
 * instead of paging from a wrong place.
 */
class CursorCodec
{
    private const FORMAT = '/^(\d{1,15})\.(\d{1,10})\.([0-9a-f]{8})$/D';

    /**
     * @param SearchCursorInterfaceFactory $cursorFactory
     * @param EncoderInterface $urlEncoder
     * @param DecoderInterface $urlDecoder
     */
    public function __construct(
        private readonly SearchCursorInterfaceFactory $cursorFactory,
        private readonly EncoderInterface $urlEncoder,
        private readonly DecoderInterface $urlDecoder
    ) {
    }

    /**
     * The token for a position
     *
     * @param SearchCursorInterface $cursor
     * @return string
     */
    public function encode(SearchCursorInterface $cursor): string
    {
        $payload = $cursor->getOccurredAtMs() . '.' . $cursor->getActivityId();

        return $this->urlEncoder->encode($payload . '.' . $this->checksum($payload));
    }

    /**
     * The position a token stands for
     *
     * @param string $token
     * @return SearchCursorInterface
     * @throws \InvalidArgumentException When the token is not one this codec made
     */
    public function decode(string $token): SearchCursorInterface
    {
        $decoded = preg_match('/^[A-Za-z0-9_~-]{1,64}$/D', $token) === 1 ? $this->urlDecoder->decode($token) : '';
        if (preg_match(self::FORMAT, $decoded, $parts) !== 1) {
            throw new \InvalidArgumentException('Malformed page position.');
        }

        if (!hash_equals($this->checksum($parts[1] . '.' . $parts[2]), $parts[3])) {
            throw new \InvalidArgumentException('Altered page position.');
        }

        return $this->cursorFactory->create()->withPosition((int) $parts[1], (int) $parts[2]);
    }

    /**
     * Checksum of a payload
     *
     * @param string $payload
     * @return string
     */
    private function checksum(string $payload): string
    {
        return substr(hash('sha256', 'activity-search-cursor:' . $payload), 0, 8);
    }
}
