<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Controller\Adminhtml\Activity;

use Hryvinskyi\ActivityLog\Api\ActivityRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Deletes one activity from its detail page; the rest of its operation is kept
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ActivityLog::delete';

    /**
     * @param Context $context
     * @param ActivityRepositoryInterface $activityRepository
     */
    public function __construct(
        Context $context,
        private readonly ActivityRepositoryInterface $activityRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute(): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $redirect->setPath('*/*/index');

        $id = $this->getRequest()->getParam('id');
        if (is_numeric($id) && $this->activityRepository->deleteByIds([(int) $id]) === 1) {
            $this->messageManager->addSuccessMessage(__('Activity #%1 was deleted.', (int) $id));
        } else {
            $this->messageManager->addErrorMessage(__('That activity no longer exists.'));
        }

        return $redirect;
    }
}
