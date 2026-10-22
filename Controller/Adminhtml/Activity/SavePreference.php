<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Controller\Adminhtml\Activity;

use Hryvinskyi\ActivityLog\Model\Search\Preference\ResultView;
use Hryvinskyi\ActivityLog\Model\Search\Preference\SearchPreferenceStorageInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;

/**
 * Remembers how the admin user sees the search page: the result view ("view": "timeline" or "grid") and
 * "Changes only" ("changes": "1" or "0"); anything else is answered 400 and nothing is kept
 */
class SavePreference extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ActivityLog::view';

    /**
     * Values of "changes" and what they mean
     */
    private const CHANGES = ['1' => true, '0' => false];

    /**
     * @param Context $context
     * @param SearchPreferenceStorageInterface $preferences
     */
    public function __construct(
        Context $context,
        private readonly SearchPreferenceStorageInterface $preferences
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute(): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $view = $this->getRequest()->getParam('view');
        $changes = $this->getRequest()->getParam('changes');
        $view = is_string($view) ? ResultView::tryFrom($view) : null;
        $changesOnly = is_string($changes) ? (self::CHANGES[$changes] ?? null) : null;

        if ($view === null || $changesOnly === null) {
            $result->setHttpResponseCode(400);

            return $result->setData(['error' => (string) __('This view choice cannot be remembered.')]);
        }

        $this->preferences->save($view, $changesOnly);

        return $result->setData(['view' => $view->value, 'changesOnly' => $changesOnly]);
    }
}
