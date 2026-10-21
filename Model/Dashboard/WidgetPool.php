<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Model\Dashboard;

use Hryvinskyi\ActivityLog\Api\DashboardWidgetInterface;
use Magento\Framework\AuthorizationInterface;

/**
 * Widgets registered through the "widgets" argument, in sortOrder, that the admin user may see
 */
class WidgetPool
{
    /**
     * @param AuthorizationInterface $authorization
     * @param array<string,DashboardWidgetInterface> $widgets
     */
    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly array $widgets = []
    ) {
    }

    /**
     * Widgets the current admin may see
     *
     * @return list<DashboardWidgetInterface>
     */
    public function visible(): array
    {
        $visible = [];
        foreach ($this->widgets as $widget) {
            $resource = $widget->getAclResource();
            if ($resource === null || $this->authorization->isAllowed($resource)) {
                $visible[] = $widget;
            }
        }

        return $visible;
    }
}
