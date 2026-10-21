<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\ResourceModel\Activity\Grid;

use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\DB\Sql\ExpressionFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface as Logger;

/**
 * Journal rows for the activity grid, each with the number of activities in its operation
 *
 * The count is a correlated subquery on the indexed correlation id, so it is computed only for the rows of the
 * page being shown and counts the whole operation, whatever the grid's filters hide.
 */
class Collection extends SearchResult
{
    /**
     * @param EntityFactory $entityFactory
     * @param Logger $logger
     * @param FetchStrategy $fetchStrategy
     * @param EventManager $eventManager
     * @param ExpressionFactory $expressionFactory
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
        private readonly ExpressionFactory $expressionFactory,
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

        $operationSize = $this->getConnection()->select()
            ->from(['operation' => $this->getMainTable()], ['COUNT(*)'])
            ->where('operation.correlation_id = main_table.correlation_id');

        $this->getSelect()->columns([
            'operation_size' => $this->expressionFactory->create(['expression' => '(' . $operationSize . ')']),
            'correlation_full' => 'main_table.correlation_id',
        ]);
        $this->addFilterToMap('entity_id', 'main_table.entity_id');
        $this->addFilterToMap('correlation_id', 'main_table.correlation_id');
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
}
