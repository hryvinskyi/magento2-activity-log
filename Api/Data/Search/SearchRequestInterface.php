<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api\Data\Search;

/**
 * What a person searches the journal for: a time range, the filters, "changes only", and the page wanted
 *
 * Immutable and valid by construction. Create one through SearchRequestInterfaceFactory with no arguments and set
 * each part with its with…() method, each of which returns a valid copy. A request from the factory covers the range
 * [0, 0], has no filters, shows every channel, starts at the newest activity and asks for DEFAULT_LIMIT activities.
 *
 * @api
 */
interface SearchRequestInterface
{
    /**
     * Most filters one request may carry; each costs a sub-select
     */
    public const MAX_CRITERIA = 20;

    /**
     * Activities per page unless asked otherwise
     */
    public const DEFAULT_LIMIT = 50;

    /**
     * Most activities per page
     */
    public const MAX_LIMIT = 200;

    /**
     * Start of the range, ms since the epoch, inclusive
     *
     * @return int
     */
    public function getFromMs(): int;

    /**
     * End of the range, ms since the epoch, inclusive
     *
     * @return int
     */
    public function getToMs(): int;

    /**
     * The filters, every one of which an activity must match
     *
     * @return list<SearchCriterionInterface>
     */
    public function getCriteria(): array;

    /**
     * Whether only activities of the channels that record changes are wanted
     *
     * @return bool
     */
    public function isChangesOnly(): bool;

    /**
     * Where the previous page ended, or null for the first page
     *
     * @return SearchCursorInterface|null
     */
    public function getCursor(): ?SearchCursorInterface;

    /**
     * Activities wanted on the page, 1 to MAX_LIMIT
     *
     * @return int
     */
    public function getLimit(): int;

    /**
     * A copy covering this range; this request is left as it is
     *
     * @param int $fromMs Zero or more
     * @param int $toMs At least $fromMs
     * @return SearchRequestInterface
     * @throws \InvalidArgumentException When the range is negative or ends before it starts
     */
    public function withRange(int $fromMs, int $toMs): SearchRequestInterface;

    /**
     * A copy with these filters in place of the current ones; this request is left as it is
     *
     * @param list<SearchCriterionInterface> $criteria At most MAX_CRITERIA, none of them blank
     * @return SearchRequestInterface
     * @throws \InvalidArgumentException When there are too many filters or one of them is blank
     */
    public function withCriteria(array $criteria): SearchRequestInterface;

    /**
     * A copy with "changes only" switched on or off; this request is left as it is
     *
     * @param bool $changesOnly
     * @return SearchRequestInterface
     */
    public function withChangesOnly(bool $changesOnly): SearchRequestInterface;

    /**
     * A copy continuing after this position, or from the newest activity when null; this request is left as it is
     *
     * @param SearchCursorInterface|null $cursor
     * @return SearchRequestInterface
     * @throws \InvalidArgumentException When the cursor is still at its blank position
     */
    public function withCursor(?SearchCursorInterface $cursor): SearchRequestInterface;

    /**
     * A copy asking for this many activities per page; this request is left as it is
     *
     * @param int $limit 1 to MAX_LIMIT
     * @return SearchRequestInterface
     * @throws \InvalidArgumentException When the limit is out of range
     */
    public function withLimit(int $limit): SearchRequestInterface;
}
