<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ActivityLog\Api;

use Hryvinskyi\ActivityLog\Api\Data\DashboardQueryInterface;
use Hryvinskyi\ActivityLog\Api\Data\WidgetViewType;

/**
 * A dashboard widget
 *
 * Register an implementation in the "widgets" argument of the dashboard widget pool, with a sortOrder. The
 * dashboard draws every widget in the same frame (title, module tag, chart/table toggle, loading, empty and error
 * states); a widget only says what to draw.
 *
 * @api
 */
interface DashboardWidgetInterface
{
    /**
     * Unique code: lowercase letters, digits, underscore
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Title, translated
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Line under the title, translated, or null
     *
     * @return string|null
     */
    public function getSubtitle(): ?string;

    /**
     * Name of the module that contributes the widget, shown as a tag, or null for the dashboard's own widgets
     *
     * @return string|null
     */
    public function getModuleTag(): ?string;

    /**
     * How the widget is drawn
     *
     * @return WidgetViewType
     */
    public function getViewType(): WidgetViewType;

    /**
     * Columns of the dashboard grid the widget spans: 1 or 2
     *
     * @return int
     */
    public function getSpan(): int;

    /**
     * ACL resource the admin user needs to see the widget, or null when the dashboard's own resource is enough
     *
     * @return string|null
     */
    public function getAclResource(): ?string;

    /**
     * Data in the shape its view type defines (WidgetViewType)
     *
     * @param DashboardQueryInterface $query
     * @return array<string, mixed>
     */
    public function getData(DashboardQueryInterface $query): array;
}
