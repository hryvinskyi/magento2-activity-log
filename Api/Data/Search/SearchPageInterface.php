<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data\Search;

/**
 * One page of search results, newest first, with the counts the page shows around it
 *
 * The counts describe the whole request, not the page, so only the first page (a request without a cursor) carries
 * them; later pages leave them null and the browser keeps those of the first page.
 *
 * @api
 */
interface SearchPageInterface
{
    /**
     * The activities of the page, newest first
     *
     * @return list<EventViewInterface>
     */
    public function getEvents(): array;

    /**
     * Where the next page starts, or null when no older match exists
     *
     * @return SearchCursorInterface|null
     */
    public function getNextCursor(): ?SearchCursorInterface;

    /**
     * Whether the value search on this page stopped at its candidate limit, so older matches may exist beyond it
     *
     * @return bool
     */
    public function isNarrowed(): bool;

    /**
     * Matches in the range: exact without filters, else counted up to a bound; null after the first page
     *
     * @return SearchCountInterface|null
     */
    public function getTotal(): ?SearchCountInterface;

    /**
     * Matches outside the range, counted up to a bound
     *
     * Present on a first page that is empty or that comes from a request with filters, else null.
     *
     * @return SearchCountInterface|null
     */
    public function getOutside(): ?SearchCountInterface;

    /**
     * Time of the newest match outside the range, ms since the epoch, or null when there is none or it was not asked
     *
     * @return int|null
     */
    public function getLatestOutsideMs(): ?int;

    /**
     * Matches in and outside the range together, for a first page of a request with filters, else null
     *
     * @return SearchCountInterface|null
     */
    public function getOverall(): ?SearchCountInterface;

    /**
     * Whether the journal holds no activity at all, which an empty page alone cannot tell
     *
     * @return bool
     */
    public function isJournalEmpty(): bool;
}
