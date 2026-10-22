<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Ui\DataProvider;

use Hryvinskyi\ActivityLog\Model\Search\RequestCondition;
use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Select;
use Magento\Framework\View\Element\UiComponent\DataProvider\FilterApplierInterface;

/**
 * Applies the activity grid's search to its journal collection as exactly one WHERE part
 *
 * The listing's filter pool joins every WHERE part one applier adds with OR, so the range, "changes only" and each
 * filter of the search go in as one condition, the one the search engine builds: two parts would let a row match
 * the range or a filter instead of both. The filter's value is the search as ActivitySearchParams wrote it; the
 * collection reads the journal under the alias "main_table".
 */
class ActivitySearchFilter implements FilterApplierInterface
{
    /**
     * Condition type of the grid's search filter, the key of this applier in the filter pool
     */
    public const CONDITION_TYPE = 'activity_search';

    /**
     * Alias of the journal in the collection's query
     */
    private const ALIAS = 'main_table';

    /**
     * @param ActivitySearchParams $searchParams
     * @param RequestCondition $requestCondition
     */
    public function __construct(
        private readonly ActivitySearchParams $searchParams,
        private readonly RequestCondition $requestCondition
    ) {
    }

    /**
     * @inheritDoc
     * @throws \InvalidArgumentException When the collection does not read the journal through a query
     */
    public function apply(Collection $collection, Filter $filter)
    {
        if (!$collection instanceof AbstractDb) {
            throw new \InvalidArgumentException('The activity search applies to a database collection only.');
        }

        $request = $this->searchParams->request($filter->getValue());

        $collection->getSelect()->where(
            $this->requestCondition->sql($request, self::ALIAS),
            null,
            Select::TYPE_CONDITION
        );
    }
}
