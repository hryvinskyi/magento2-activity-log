<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Ui\Component;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentInterface;
use Magento\Ui\Component\MassAction;

/**
 * Mass actions that leave out each action whose "aclResource" the admin user is not allowed
 */
class AclMassAction extends MassAction
{
    /**
     * @param AuthorizationInterface $authorization
     * @param ContextInterface $context
     * @param array<string,UiComponentInterface> $components
     * @param array<mixed> $data
     */
    public function __construct(
        private readonly AuthorizationInterface $authorization,
        ContextInterface $context,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $components, $data);
    }

    /**
     * @inheritDoc
     */
    public function prepare()
    {
        foreach ($this->getChildComponents() as $name => $action) {
            $resource = $action->getConfiguration()['aclResource'] ?? null;
            if (is_string($resource) && $resource !== '' && !$this->authorization->isAllowed($resource)) {
                unset($this->components[$name]);
            }
        }

        parent::prepare();
    }
}
