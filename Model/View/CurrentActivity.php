<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\View;

use Hryvinskyi\ActivityLog\Api\ActivityRepositoryInterface;
use Hryvinskyi\ActivityLog\Api\Data\ActivityInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * The activity the detail page shows: the "id" request parameter, loaded once per request
 *
 * The controller and the page's view models read the same instance, so the activity is loaded once.
 */
class CurrentActivity implements ResetAfterRequestInterface
{
    /**
     * @var bool
     */
    private bool $loaded = false;
    /**
     * @var ActivityInterface|null
     */
    private ?ActivityInterface $activity = null;

    /**
     * @param RequestInterface $request
     * @param ActivityRepositoryInterface $activityRepository
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ActivityRepositoryInterface $activityRepository
    ) {
    }

    /**
     * The requested id, or null when the request names none
     *
     * @return int|null
     */
    public function requestedId(): ?int
    {
        $id = $this->request->getParam('id');

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * The requested activity, or null when it does not exist
     *
     * @return ActivityInterface|null
     */
    public function get(): ?ActivityInterface
    {
        if ($this->loaded) {
            return $this->activity;
        }

        $this->loaded = true;
        $id = $this->requestedId();
        if ($id === null) {
            return null;
        }

        try {
            $this->activity = $this->activityRepository->get($id);
        } catch (NoSuchEntityException) {
            $this->activity = null;
        }

        return $this->activity;
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->loaded = false;
        $this->activity = null;
    }
}
