<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Controller\Adminhtml\Activity;

use Hryvinskyi\ActivityLog\Api\Search\ActivitySearchInterface;
use Hryvinskyi\ActivityLog\Model\Search\InvalidSearchRequestException;
use Hryvinskyi\ActivityLog\Model\Search\PagePresenter;
use Hryvinskyi\ActivityLog\Model\Search\RequestParser;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;

/**
 * One page of journal search results as JSON; a malformed request is answered 400 with the reason
 */
class Search extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Hryvinskyi_ActivityLog::view';

    /**
     * @param Context $context
     * @param RequestParser $requestParser
     * @param ActivitySearchInterface $activitySearch
     * @param PagePresenter $pagePresenter
     */
    public function __construct(
        Context $context,
        private readonly RequestParser $requestParser,
        private readonly ActivitySearchInterface $activitySearch,
        private readonly PagePresenter $pagePresenter
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

        try {
            $request = $this->requestParser->parse($this->getRequest()->getParams());
        } catch (InvalidSearchRequestException $invalid) {
            $result->setHttpResponseCode(400);

            return $result->setData(['error' => $invalid->getReason()]);
        }

        return $result->setData($this->pagePresenter->present($this->activitySearch->search($request)));
    }
}
