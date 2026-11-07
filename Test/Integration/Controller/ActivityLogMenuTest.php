<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Test\Integration\Controller;

use Hryvinskyi\ActivityLog\Test\Integration\Controller\Fixture\ActivityLogMenu;
use Magento\TestFramework\Response;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The entries this module puts into the Activity Log menu, as the dashboard prints the menu
 *
 * The menu declares the groups Logs, Pages and Settings; this module's entries sit in Logs and Settings. A group is
 * printed only while an entry the admin may see is in it, so Pages, which other modules fill, is not asserted here.
 * Each test dispatches once: the test application serves a second dispatch of one test from the first one's state.
 */
class ActivityLogMenuTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'Hryvinskyi_ActivityLog::dashboard';

    /**
     * @var string
     */
    protected $uri = 'backend/activitylog/dashboard/index';

    public function testLogsComesBeforeSettings(): void
    {
        $headings = $this->menu()->headings();

        $logs = array_search('Logs', $headings, true);
        $settings = array_search('Settings', $headings, true);
        self::assertIsInt($logs, 'the menu has a Logs group');
        self::assertIsInt($settings, 'the menu has a Settings group');
        self::assertLessThan($settings, $logs, 'Logs comes before Settings');
    }

    public function testTheDashboardAndAllActivityAreLogs(): void
    {
        $menu = $this->menu();

        $dashboard = $menu->entry('Dashboard');
        self::assertSame('Logs', $dashboard['heading']);
        self::assertStringContainsString('/activitylog/dashboard/index/', $dashboard['href']);
        self::assertTrue($dashboard['icon'], 'Dashboard has an icon');

        $all = $menu->entry('All activity');
        self::assertSame('Logs', $all['heading']);
        self::assertStringContainsString('/activitylog/activity/index/', $all['href']);
        self::assertTrue($all['icon'], 'All activity has an icon');
    }

    public function testTheModuleSettingsAreInSettings(): void
    {
        $settings = $this->menu()->entry('Activity log');

        self::assertSame('Settings', $settings['heading']);
        self::assertStringContainsString('/system_config/edit/section/hryvinskyi_activity_log/', $settings['href']);
        self::assertTrue($settings['icon'], 'Activity log has an icon');
    }

    /**
     * The Activity Log menu of the dashboard, which must have been answered
     *
     * @return ActivityLogMenu
     */
    private function menu(): ActivityLogMenu
    {
        $this->dispatch($this->uri);
        $response = $this->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getHttpResponseCode());

        return new ActivityLogMenu((string) $response->getBody());
    }
}
