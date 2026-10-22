<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCriterionInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCursorInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterface;

/**
 * Immutable search request, created through SearchRequestInterfaceFactory with no arguments and set up through its
 * with…() methods, so nothing a person typed passes through the object manager's argument handling
 */
class SearchRequest implements SearchRequestInterface
{
    /**
     * @var int
     */
    private int $fromMs = 0;

    /**
     * @var int
     */
    private int $toMs = 0;

    /**
     * @var list<SearchCriterionInterface>
     */
    private array $criteria = [];

    /**
     * @var bool
     */
    private bool $changesOnly = false;

    /**
     * @var SearchCursorInterface|null
     */
    private ?SearchCursorInterface $cursor = null;

    /**
     * @var int
     */
    private int $limit = self::DEFAULT_LIMIT;

    /**
     * @inheritDoc
     */
    public function getFromMs(): int
    {
        return $this->fromMs;
    }

    /**
     * @inheritDoc
     */
    public function getToMs(): int
    {
        return $this->toMs;
    }

    /**
     * @inheritDoc
     */
    public function getCriteria(): array
    {
        return $this->criteria;
    }

    /**
     * @inheritDoc
     */
    public function isChangesOnly(): bool
    {
        return $this->changesOnly;
    }

    /**
     * @inheritDoc
     */
    public function getCursor(): ?SearchCursorInterface
    {
        return $this->cursor;
    }

    /**
     * @inheritDoc
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @inheritDoc
     */
    public function withRange(int $fromMs, int $toMs): SearchRequestInterface
    {
        if ($fromMs < 0 || $toMs < $fromMs) {
            throw new \InvalidArgumentException(
                sprintf('A search range runs from 0 or later to its start or later, not %d to %d.', $fromMs, $toMs)
            );
        }

        $copy = clone $this;
        $copy->fromMs = $fromMs;
        $copy->toMs = $toMs;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withCriteria(array $criteria): SearchRequestInterface
    {
        if (count($criteria) > self::MAX_CRITERIA) {
            throw new \InvalidArgumentException(
                sprintf('A search takes at most %d filters, not %d.', self::MAX_CRITERIA, count($criteria))
            );
        }

        foreach ($criteria as $criterion) {
            if ($criterion->getValue() === '') {
                throw new \InvalidArgumentException('A search filter needs a value; this one is blank.');
            }
        }

        $copy = clone $this;
        $copy->criteria = $criteria;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withChangesOnly(bool $changesOnly): SearchRequestInterface
    {
        $copy = clone $this;
        $copy->changesOnly = $changesOnly;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withCursor(?SearchCursorInterface $cursor): SearchRequestInterface
    {
        if ($cursor !== null && $cursor->getActivityId() < 1) {
            throw new \InvalidArgumentException('A page position needs the id of the last activity shown.');
        }

        $copy = clone $this;
        $copy->cursor = $cursor;

        return $copy;
    }

    /**
     * @inheritDoc
     */
    public function withLimit(int $limit): SearchRequestInterface
    {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException(
                sprintf('A page holds 1 to %d activities, not %d.', self::MAX_LIMIT, $limit)
            );
        }

        $copy = clone $this;
        $copy->limit = $limit;

        return $copy;
    }
}
