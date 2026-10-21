<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Controller;

use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The icons of the grid and dashboard pages, rendered through the real admin stack
 */
class PageIconsTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ActivityLog::view';

    /**
     * @var string
     */
    protected $uri = 'backend/activitylog/activity/index';

    public function testTheGridPageButtonsAndMenuCarryTheirIcons(): void
    {
        $this->dispatch('backend/activitylog/activity/index');
        $html = $this->body();

        self::assertStringContainsString('class="activity-button__icon lucide lucide-layout-dashboard"', $html);
        self::assertStringContainsString('class="activity-button__icon lucide lucide-download"', $html);
        self::assertStringContainsString('class="menu-item-icon lucide lucide-list"', $html);
        self::assertStringContainsString('class="menu-item-icon lucide lucide-settings"', $html);
    }

    public function testTheDashboardPrintsItsButtonIconAndOneTrendIconPerDirection(): void
    {
        $this->dispatch('backend/activitylog/dashboard/index');
        $html = $this->body();

        self::assertStringContainsString('class="activity-button__icon lucide lucide-list"', $html);
        foreach (['up' => 'trending-up', 'down' => 'trending-down', 'flat' => 'minus'] as $direction => $icon) {
            self::assertMatchesRegularExpression(
                sprintf(
                    '#<template data-role="trend-icon" data-direction="%s">\s*<svg [^>]*class="activity-card__trend '
                    . 'lucide lucide-%s"#',
                    $direction,
                    $icon
                ),
                $html
            );
        }
    }

    /**
     * @return string
     */
    private function body(): string
    {
        $response = $this->getResponse();
        self::assertInstanceOf(\Magento\TestFramework\Response::class, $response);

        return (string) $response->getBody();
    }
}
