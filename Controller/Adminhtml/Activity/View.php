<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Controller\Adminhtml\Activity;

use Hryvinskyi\ActivityLog\Model\View\CurrentActivity;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * One activity with the rest of its operation; the module that owns the activity's channel adds its detail panel
 * through the layout handle hryvinskyi_activitylog_activity_view_channel_{code}
 */
class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ActivityLog::view';
    public const CHANNEL_HANDLE_PREFIX = 'hryvinskyi_activitylog_activity_view_channel_';

    /**
     * @param Context $context
     * @param CurrentActivity $currentActivity
     */
    public function __construct(
        Context $context,
        private readonly CurrentActivity $currentActivity
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        if (!$page instanceof Page) {
            return $page;
        }

        // The channel's handle goes in first: setting the active menu builds the layout, and a handle added after
        // that is never loaded
        $activity = $this->currentActivity->get();
        if ($activity !== null) {
            $page->addHandle(self::CHANNEL_HANDLE_PREFIX . $activity->getChannel());
        }

        $page->setActiveMenu('Hryvinskyi_ActivityLog::activity_grid');
        $page->getConfig()->getTitle()->prepend(
            $activity === null ? (string) __('Activity not found') : (string) __('Activity #%1', $activity->getId())
        );

        return $page;
    }
}
