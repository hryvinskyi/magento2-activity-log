<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Controller\Adminhtml\Dashboard;

use Hryvinskyi\ActivityLog\Model\Dashboard\PreferenceStorageInterface;
use Hryvinskyi\ActivityLog\Model\Dashboard\QueryResolver;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;

/**
 * Remembers the period and channels the admin user chose; only valid values are kept
 */
class SavePreference extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ActivityLog::dashboard';

    /**
     * @param Context $context
     * @param QueryResolver $queryResolver
     * @param PreferenceStorageInterface $preferences
     */
    public function __construct(
        Context $context,
        private readonly QueryResolver $queryResolver,
        private readonly PreferenceStorageInterface $preferences
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
        $this->preferences->save($query->getPeriodDays(), $query->getChannels());

        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        return $result->setData(['periodDays' => $query->getPeriodDays(), 'channels' => $query->getChannels()]);
    }
}
