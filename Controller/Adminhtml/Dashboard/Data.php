<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Controller\Adminhtml\Dashboard;

use Hryvinskyi\ActivityLog\Model\Dashboard\DashboardData;
use Hryvinskyi\ActivityLog\Model\Dashboard\QueryResolver;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;

/**
 * Dashboard figures for ?period=7|14|30&channels[]=…
 */
class Data extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ActivityLog::dashboard';

    /**
     * @param Context $context
     * @param QueryResolver $queryResolver
     * @param DashboardData $dashboardData
     */
    public function __construct(
        Context $context,
        private readonly QueryResolver $queryResolver,
        private readonly DashboardData $dashboardData
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute(): Json
    {
        $query = $this->queryResolver->resolve(
            $this->getRequest()->getParam('period'),
            $this->getRequest()->getParam('channels')
        );

        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        return $result->setData($this->dashboardData->forQuery($query));
    }
}
