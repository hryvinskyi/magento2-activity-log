<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Data\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\EventViewInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCountInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCursorInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchPageInterface;

/**
 * Immutable page of results, created by the search through SearchPageInterfaceFactory from what it found
 */
class SearchPage implements SearchPageInterface
{
    /**
     * @param list<EventViewInterface> $events
     * @param SearchCursorInterface|null $nextCursor
     * @param bool $narrowed
     * @param SearchCountInterface|null $total
     * @param SearchCountInterface|null $outside
     * @param int|null $latestOutsideMs
     * @param SearchCountInterface|null $overall
     * @param bool $journalEmpty
     */
    public function __construct(
        private readonly array $events = [],
        private readonly ?SearchCursorInterface $nextCursor = null,
        private readonly bool $narrowed = false,
        private readonly ?SearchCountInterface $total = null,
        private readonly ?SearchCountInterface $outside = null,
        private readonly ?int $latestOutsideMs = null,
        private readonly ?SearchCountInterface $overall = null,
        private readonly bool $journalEmpty = false
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @inheritDoc
     */
    public function getNextCursor(): ?SearchCursorInterface
    {
        return $this->nextCursor;
    }

    /**
     * @inheritDoc
     */
    public function isNarrowed(): bool
    {
        return $this->narrowed;
    }

    /**
     * @inheritDoc
     */
    public function getTotal(): ?SearchCountInterface
    {
        return $this->total;
    }

    /**
     * @inheritDoc
     */
    public function getOutside(): ?SearchCountInterface
    {
        return $this->outside;
    }

    /**
     * @inheritDoc
     */
    public function getLatestOutsideMs(): ?int
    {
        return $this->latestOutsideMs;
    }

    /**
     * @inheritDoc
     */
    public function getOverall(): ?SearchCountInterface
    {
        return $this->overall;
    }

    /**
     * @inheritDoc
     */
    public function isJournalEmpty(): bool
    {
        return $this->journalEmpty;
    }
}
