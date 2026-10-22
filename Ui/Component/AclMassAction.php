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
 * Mass actions that leave out each action the admin user is not allowed
 *
 * An action names what it needs in its config: "aclResource", one resource, or "aclResources", a list of resources
 * the admin must hold every one of (an action whose controller checks more than one). An action naming neither is
 * kept. A listing of any module uses it as its massaction's class.
 *
 * @api
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
            if (!$this->isAllowed($action->getConfiguration())) {
                unset($this->components[$name]);
            }
        }

        parent::prepare();
    }

    /**
     * Whether the admin holds every resource an action's config names
     *
     * @param array<mixed> $config
     * @return bool
     */
    private function isAllowed(array $config): bool
    {
        $resources = $config['aclResources'] ?? [];
        $resources = is_array($resources) ? array_values($resources) : [];
        $resources[] = $config['aclResource'] ?? null;
        foreach ($resources as $resource) {
            if (is_string($resource) && $resource !== '' && !$this->authorization->isAllowed($resource)) {
                return false;
            }
        }

        return true;
    }
}
