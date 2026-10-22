<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\EventViewInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCountInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchPageInterface;

/**
 * A result page as the JSON the search page reads
 *
 * Counts are {count, atLeast}; times are ms since the epoch for the browser to format in the store's timezone; the
 * next page position is an opaque token to send back as "cursor".
 */
class PagePresenter
{
    /**
     * @param CursorCodec $cursorCodec
     * @param EventPresenter $eventPresenter
     */
    public function __construct(
        private readonly CursorCodec $cursorCodec,
        private readonly EventPresenter $eventPresenter
    ) {
    }

    /**
     * The page as plain data
     *
     * @param SearchPageInterface $page
     * @return array<string, mixed>
     */
    public function present(SearchPageInterface $page): array
    {
        $nextCursor = $page->getNextCursor();

        return [
            'events' => array_map(
                fn (EventViewInterface $event): array => $this->eventPresenter->present($event),
                $page->getEvents()
            ),
            'nextCursor' => $nextCursor === null ? null : $this->cursorCodec->encode($nextCursor),
            'narrowed' => $page->isNarrowed(),
            'total' => $this->count($page->getTotal()),
            'outside' => $this->count($page->getOutside()),
            'latestOutsideMs' => $page->getLatestOutsideMs(),
            'overall' => $this->count($page->getOverall()),
            'journalEmpty' => $page->isJournalEmpty(),
        ];
    }

    /**
     * A count, or null when the page has none
     *
     * @param SearchCountInterface|null $count
     * @return array{count: int, atLeast: bool}|null
     */
    private function count(?SearchCountInterface $count): ?array
    {
        return $count === null ? null : ['count' => $count->getCount(), 'atLeast' => $count->isAtLeast()];
    }
}
