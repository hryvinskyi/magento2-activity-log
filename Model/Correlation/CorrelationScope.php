<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Correlation;

use Hryvinskyi\ActivityLog\Api\CorrelationScopeInterface;
use Hryvinskyi\ActivityLog\Model\Data\Activity;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * Request-wide correlation id with a stack of nested operations on top of it
 *
 * The request id is the caller's X-Correlation-Id when it is well formed, otherwise a new one.
 */
class CorrelationScope implements CorrelationScopeInterface, ResetAfterRequestInterface
{
    /**
     * @var string|null
     */
    private ?string $requestId = null;

    /**
     * @var list<string>
     */
    private array $open = [];

    /**
     * @param IdGeneratorInterface $idGenerator
     * @param IncomingIdInterface $incomingId
     */
    public function __construct(
        private readonly IdGeneratorInterface $idGenerator,
        private readonly IncomingIdInterface $incomingId
    ) {
    }

    /**
     * @inheritDoc
     */
    public function current(): string
    {
        $innermost = end($this->open);

        return $innermost !== false ? $innermost : $this->requestId();
    }

    /**
     * @inheritDoc
     */
    public function begin(): string
    {
        $id = $this->idGenerator->next();
        $this->open[] = $id;

        return $id;
    }

    /**
     * @inheritDoc
     */
    public function end(string $correlationId): void
    {
        $position = array_search($correlationId, $this->open, true);
        if ($position === false) {
            return;
        }

        $this->open = array_slice($this->open, 0, (int) $position);
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->requestId = null;
        $this->open = [];
    }

    /**
     * The request's own correlation id
     *
     * @return string
     */
    private function requestId(): string
    {
        if ($this->requestId !== null) {
            return $this->requestId;
        }

        $incoming = $this->incomingId->read();
        $this->requestId = $incoming !== null && preg_match(Activity::CORRELATION_PATTERN, $incoming) === 1
            ? $incoming
            : $this->idGenerator->next();

        return $this->requestId;
    }
}
