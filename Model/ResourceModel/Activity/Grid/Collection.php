<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\ResourceModel\Activity\Grid;

use Hryvinskyi\ActivityLog\Model\Search\Grid\PageColumns;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface as Logger;

/**
 * Journal rows for the activity grid
 *
 * A page that is read gets the grid's computed columns afterwards (the size of each row's operation, what changed),
 * one query per page. A collection read whole — the ids of a mass action — gets none: those columns are for reading a
 * page, and asking for them over every match would cost a query over the whole result.
 */
class Collection extends SearchResult implements BoundedCountInterface
{
    /**
     * @var string The grid's own document type
     */
    protected $document = ActivityDocument::class;

    /**
     * @param EntityFactory $entityFactory
     * @param Logger $logger
     * @param FetchStrategy $fetchStrategy
     * @param EventManager $eventManager
     * @param PageColumns $pageColumns
     * @param string $mainTable
     * @param string|null $resourceModel
     * @param string|null $identifierName
     * @param string|null $connectionName
     */
    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        private readonly PageColumns $pageColumns,
        $mainTable,
        $resourceModel = null,
        $identifierName = null,
        $connectionName = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel,
            $identifierName,
            $connectionName
        );
    }

    /**
     * @inheritDoc
     */
    protected function _initSelect()
    {
        parent::_initSelect();

        $this->getSelect()->columns(['correlation_full' => 'main_table.correlation_id']);
        $this->addFilterToMap('entity_id', 'main_table.entity_id');
        $this->addFilterToMap('correlation_id', 'main_table.correlation_id');
    }

    /**
     * @inheritDoc
     */
    public function countUpTo(int $bound): int
    {
        $this->_renderFilters();
        $rows = clone $this->getSelect();
        $rows->reset(Select::ORDER)
            ->reset(Select::LIMIT_COUNT)
            ->reset(Select::LIMIT_OFFSET)
            ->reset(Select::COLUMNS)
            ->columns(['entity_id' => 'main_table.entity_id'])
            ->limit(max(0, $bound) + 1);

        $count = $this->getConnection()->fetchOne(
            $this->getConnection()->select()->from(['bounded' => $rows], ['COUNT(*)'])
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * Order rows by the asked column, newest id first among equals
     *
     * Activities that happened in the same millisecond keep their recorded order
     *
     * @inheritDoc
     */
    protected function _renderOrders()
    {
        parent::_renderOrders();
        $this->getSelect()->order('main_table.entity_id DESC');

        return $this;
    }

    /**
     * Add the grid's computed columns to a page that was read
     *
     * @return $this
     */
    protected function _afterLoad()
    {
        parent::_afterLoad();

        // A collection read whole has no page size; a size of false or null counts as none
        if ($this->getPageSize() > 0) {
            $this->pageColumns->fill(array_values($this->_items));
        }

        return $this;
    }
}
