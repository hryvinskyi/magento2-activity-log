<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Controller\Adminhtml\Activity;

use Hryvinskyi\ActivityLog\Model\Search\SuggestionsPresenter;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;

/**
 * The search keys and the values the search bar suggests for them, as JSON, read once by the search page
 */
class Suggest extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ActivityLog::view';

    /**
     * @param Context $context
     * @param SuggestionsPresenter $suggestionsPresenter
     */
    public function __construct(
        Context $context,
        private readonly SuggestionsPresenter $suggestionsPresenter
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
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);

        return $result->setData($this->suggestionsPresenter->present());
    }
}
