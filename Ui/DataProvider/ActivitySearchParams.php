<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Ui\DataProvider;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterface;
use Hryvinskyi\ActivityLog\Api\MillisecondClockInterface;
use Hryvinskyi\ActivityLog\Model\Search\InvalidSearchRequestException;
use Hryvinskyi\ActivityLog\Model\Search\RecentDays;
use Hryvinskyi\ActivityLog\Model\Search\RequestParser;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * The search the activity grid shows, carried from the grid's request parameter to the filter that applies it
 *
 * The page sends the search as the listing parameter PARAM, in the parameters of the search endpoint: "from", "to",
 * "c" (the filters) and "changes". The grid pages and sorts itself, so a page position or size in it is ignored. A
 * request without the parameter shows the default search: the last DEFAULT_DAYS days, changes only, never the whole
 * journal. Malformed parameters are refused with the reason the endpoint gives, never shown as some other search.
 */
class ActivitySearchParams
{
    /**
     * Name of the listing request parameter holding the search
     */
    public const PARAM = 'activity_search';

    /**
     * Days the default search covers
     */
    public const DEFAULT_DAYS = 7;

    /**
     * Search parameters the grid reads
     */
    private const SEARCH_PARAMS = ['from', 'to', 'c', 'changes'];

    /**
     * @param RequestParser $requestParser
     * @param RecentDays $recentDays
     * @param MillisecondClockInterface $clock
     * @param Json $json
     */
    public function __construct(
        private readonly RequestParser $requestParser,
        private readonly RecentDays $recentDays,
        private readonly MillisecondClockInterface $clock,
        private readonly Json $json
    ) {
    }

    /**
     * The search a request parameter asks for, as the value of the grid's search filter
     *
     * @param mixed $param The listing request's PARAM; null or empty for the default search
     * @return string
     * @throws InvalidSearchRequestException When the parameter is not a valid search
     */
    public function filterValue(mixed $param): string
    {
        $params = $param === null || $param === '' ? $this->defaultParams() : $param;
        if (!is_array($params)) {
            throw new InvalidSearchRequestException(__('The search the grid was asked for is not readable.'));
        }

        $params = array_intersect_key($params, array_flip(self::SEARCH_PARAMS));
        $this->requestParser->parse($params);
        $value = $this->json->serialize($params);
        if (!is_string($value)) {
            throw new InvalidSearchRequestException(__('The search the grid was asked for is not readable.'));
        }

        return $value;
    }

    /**
     * The search a filter value holds
     *
     * @param string $value
     * @return SearchRequestInterface
     * @throws InvalidSearchRequestException When the value is not a search
     */
    public function request(string $value): SearchRequestInterface
    {
        try {
            $params = $this->json->unserialize($value);
        } catch (\InvalidArgumentException) {
            $params = null;
        }
        if (!is_array($params)) {
            throw new InvalidSearchRequestException(__('The search the grid was asked for is not readable.'));
        }

        return $this->requestParser->parse($params);
    }

    /**
     * The default search: the last DEFAULT_DAYS days, changes only
     *
     * @return array<string, string>
     */
    private function defaultParams(): array
    {
        $nowMs = $this->clock->nowMs();

        return [
            'from' => (string) $this->recentDays->startMs(self::DEFAULT_DAYS, $nowMs),
            'to' => (string) $nowMs,
            'changes' => '1',
        ];
    }
}
