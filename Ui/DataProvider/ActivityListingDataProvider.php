<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Ui\DataProvider;

use Hryvinskyi\ActivityLog\Api\Data\Search\SearchRequestInterface;
use Hryvinskyi\ActivityLog\Model\ResourceModel\Activity\Grid\BoundedCountInterface;
use Hryvinskyi\ActivityLog\Model\Search\RequestCondition;
use Hryvinskyi\ActivityLog\Model\Search\SearchCounter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

/**
 * The activity grid's rows: the activities of the search the page asks for, and how many there are
 *
 * The search comes as the listing parameter ActivitySearchParams::PARAM — the same parameters the search endpoint
 * reads — and is added to the grid's criteria as one filter, so the grid shows the activities the timeline shows,
 * paged and sorted its own way. Without the parameter the grid shows the default search.
 *
 * A rendered grid says how many rows match up to SearchCounter::BOUND and "more than that" beyond, reading no row
 * past the bound; it says "at least" too when a value search was matched among the capped candidates only, as the
 * timeline's counts do. An export reads the result itself, with its exact total, and so pages through every match.
 *
 * A page holds at most SearchRequestInterface::MAX_LIMIT rows, as a page of the search endpoint does, whatever size
 * the request asks for: the grid's rows each carry their change columns, read per page.
 */
class ActivityListingDataProvider extends DataProvider
{
    /**
     * The search filter's value, once it is among the criteria
     *
     * @var string|null
     */
    private ?string $searchValue = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param ActivitySearchParams $searchParams
     * @param RequestCondition $requestCondition
     * @param array<mixed> $meta
     * @param array<mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        private readonly ActivitySearchParams $searchParams,
        private readonly RequestCondition $requestCondition,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
    }

    /**
     * The page asked for, its size kept between 1 and SearchRequestInterface::MAX_LIMIT rows
     *
     * @param int|string $offset The page number
     * @param int|string $size Rows per page
     * @return void
     */
    public function setLimit($offset, $size)
    {
        $rows = is_numeric($size) ? (int) $size : SearchRequestInterface::MAX_LIMIT;

        parent::setLimit(
            is_numeric($offset) ? (int) $offset : 1,
            max(1, min($rows, SearchRequestInterface::MAX_LIMIT))
        );
    }

    /**
     * The criteria, with the search the request asks for
     *
     * @return \Magento\Framework\Api\Search\SearchCriteria
     * @throws \Hryvinskyi\ActivityLog\Model\Search\InvalidSearchRequestException When the search is malformed
     */
    public function getSearchCriteria()
    {
        if ($this->searchValue === null) {
            $this->searchValue = $this->searchParams->filterValue(
                $this->request->getParam(ActivitySearchParams::PARAM)
            );
            $this->addFilter(
                $this->filterBuilder
                    ->setField($this->getPrimaryFieldName())
                    ->setConditionType(ActivitySearchFilter::CONDITION_TYPE)
                    ->setValue($this->searchValue)
                    ->create()
            );
        }

        return parent::getSearchCriteria();
    }

    /**
     * The page of rows and a total that stops at the bound, with "totalAtLeast" set when more rows match
     *
     * @return array<mixed>
     */
    public function getData()
    {
        $searchResult = $this->getSearchResult();
        if (!$searchResult instanceof BoundedCountInterface) {
            return $this->searchResultToOutput($searchResult);
        }

        $items = [];
        foreach ($searchResult->getItems() as $item) {
            $row = [];
            foreach ($item->getCustomAttributes() ?? [] as $attribute) {
                $row[$attribute->getAttributeCode()] = $attribute->getValue();
            }
            $items[] = $row;
        }
        $count = $searchResult->countUpTo(SearchCounter::BOUND);

        return [
            'items' => $items,
            'totalRecords' => min($count, SearchCounter::BOUND),
            'totalAtLeast' => $count > SearchCounter::BOUND || $this->isSearchNarrowed(),
        ];
    }

    /**
     * Whether the search's value filters were matched among the capped candidates only
     *
     * @return bool
     */
    private function isSearchNarrowed(): bool
    {
        return $this->searchValue !== null
            && $this->requestCondition->isNarrowed($this->searchParams->request($this->searchValue));
    }
}
