<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Search;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCriterionInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCriterionInterfaceFactory;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchCursorInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterface;
use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterfaceFactory;

/**
 * Reads a search request from query parameters, refusing anything malformed with a translated reason
 *
 * Parameters: "from" and "to" (ms since the epoch, both included), "c" (a list of "key:value" filters, split at the
 * first colon), "changes" ("1" for changes only, "0" or absent for everything), "cursor" (a page position this
 * module issued) and "limit" (1 to 200, 50 when absent). Nothing a person typed reaches a factory's arguments: the
 * request and its filters are created empty and filled through their with…() methods.
 *
 * At most MAX_TEXT_CRITERIA filters may be plain text, counting those whose key nothing serves (they are searched as
 * text): each word of each is a group of sub-selects.
 */
class RequestParser
{
    /**
     * Most plain-text filters of one search
     */
    public const MAX_TEXT_CRITERIA = 8;

    /**
     * @param SearchRequestInterfaceFactory $requestFactory
     * @param SearchCriterionInterfaceFactory $criterionFactory
     * @param CursorCodec $cursorCodec
     * @param ConditionPool $conditionPool
     */
    public function __construct(
        private readonly SearchRequestInterfaceFactory $requestFactory,
        private readonly SearchCriterionInterfaceFactory $criterionFactory,
        private readonly CursorCodec $cursorCodec,
        private readonly ConditionPool $conditionPool
    ) {
    }

    /**
     * The request the parameters describe
     *
     * @param array<mixed> $params
     * @return SearchRequestInterface
     * @throws InvalidSearchRequestException When a parameter is missing or malformed
     */
    public function parse(array $params): SearchRequestInterface
    {
        $fromMs = $this->milliseconds($params['from'] ?? null);
        $toMs = $this->milliseconds($params['to'] ?? null);
        if ($fromMs === null || $toMs === null) {
            throw new InvalidSearchRequestException(__('The search range needs a start and an end.'));
        }
        if ($fromMs > $toMs) {
            throw new InvalidSearchRequestException(__('The search range ends before it starts.'));
        }

        return $this->requestFactory->create()
            ->withRange($fromMs, $toMs)
            ->withCriteria($this->criteria($params['c'] ?? []))
            ->withChangesOnly($this->changesOnly($params['changes'] ?? null))
            ->withCursor($this->cursor($params['cursor'] ?? null))
            ->withLimit($this->limit($params['limit'] ?? null));
    }

    /**
     * A time in ms from a parameter, or null when it is not a whole number of 0 or more
     *
     * @param mixed $value
     * @return int|null
     */
    private function milliseconds(mixed $value): ?int
    {
        return is_string($value) && preg_match('/^\d{1,15}$/D', $value) === 1 ? (int) $value : null;
    }

    /**
     * The filters
     *
     * @param mixed $value
     * @return list<SearchCriterionInterface>
     * @throws InvalidSearchRequestException
     */
    private function criteria(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidSearchRequestException(__('The search filters are not a list.'));
        }
        if (count($value) > SearchRequestInterface::MAX_CRITERIA) {
            throw new InvalidSearchRequestException(
                __(
                    'A search takes at most %1 filters. Remove some and search again.',
                    SearchRequestInterface::MAX_CRITERIA
                )
            );
        }

        $criteria = [];
        $texts = 0;
        foreach ($value as $chip) {
            $criterion = $this->criterion($chip);
            $key = $criterion->getKey();
            if ($key === SearchCriterionInterface::TEXT_KEY || !$this->conditionPool->serves($key)) {
                $texts++;
            }
            $criteria[] = $criterion;
        }

        if ($texts > self::MAX_TEXT_CRITERIA) {
            throw new InvalidSearchRequestException(
                __(
                    'A search takes at most %1 text filters. Remove some and search again.',
                    self::MAX_TEXT_CRITERIA
                )
            );
        }

        return $criteria;
    }

    /**
     * One "key:value" filter
     *
     * @param mixed $chip
     * @return SearchCriterionInterface
     * @throws InvalidSearchRequestException
     */
    private function criterion(mixed $chip): SearchCriterionInterface
    {
        $parts = is_string($chip) ? array_map('trim', explode(':', $chip, 2)) : [];
        if (count($parts) !== 2
            || preg_match(SearchCriterionInterface::KEY_PATTERN, $parts[0]) !== 1
            || $parts[1] === ''
            || mb_strlen($parts[1]) > SearchCriterionInterface::MAX_VALUE_LENGTH
        ) {
            throw new InvalidSearchRequestException(
                __(
                    'Each search filter is a key and a value of 1 to %1 characters, such as "by: jane".',
                    SearchCriterionInterface::MAX_VALUE_LENGTH
                )
            );
        }

        return $this->criterionFactory->create()->withTerm($parts[0], $parts[1]);
    }

    /**
     * The "changes only" switch
     *
     * @param mixed $value
     * @return bool
     * @throws InvalidSearchRequestException
     */
    private function changesOnly(mixed $value): bool
    {
        if ($value === null || $value === '0' || $value === '1') {
            return $value === '1';
        }

        throw new InvalidSearchRequestException(__('"Changes only" is either on or off.'));
    }

    /**
     * The page position, or null for the first page
     *
     * @param mixed $value
     * @return SearchCursorInterface|null
     * @throws InvalidSearchRequestException
     */
    private function cursor(mixed $value): ?SearchCursorInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return $this->cursorCodec->decode(is_string($value) ? $value : '');
        } catch (\InvalidArgumentException) {
            throw new InvalidSearchRequestException(
                __('The page position is not valid. Run the search again from the start.')
            );
        }
    }

    /**
     * The page size
     *
     * @param mixed $value
     * @return int
     * @throws InvalidSearchRequestException
     */
    private function limit(mixed $value): int
    {
        if ($value === null) {
            return SearchRequestInterface::DEFAULT_LIMIT;
        }

        $limit = is_string($value) && preg_match('/^\d{1,3}$/D', $value) === 1 ? (int) $value : 0;
        if ($limit < 1 || $limit > SearchRequestInterface::MAX_LIMIT) {
            throw new InvalidSearchRequestException(
                __('A page shows 1 to %1 activities.', SearchRequestInterface::MAX_LIMIT)
            );
        }

        return $limit;
    }
}
