/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The renderer for each widget view type. The set is closed: it matches the server's WidgetViewType, so a widget
 * of any module is drawn by one of these.
 */
define([
    'Hryvinskyi_ActivityLog/js/dashboard/renderers/chart',
    'Hryvinskyi_ActivityLog/js/dashboard/renderers/table',
    'Hryvinskyi_ActivityLog/js/dashboard/renderers/number'
], function (chartRenderer, tableRenderer, numberRenderer) {
    'use strict';

    var RENDERERS = Object.freeze({
        line: chartRenderer('line'),
        bar: chartRenderer('bar'),
        table: tableRenderer,
        number: numberRenderer
    });

    /**
     * @param {string} viewType
     * @returns {?{render: Function, isEmpty: Function}} Null for a view type this page does not know
     */
    return function rendererFor(viewType) {
        return Object.prototype.hasOwnProperty.call(RENDERERS, viewType) ? RENDERERS[viewType] : null;
    };
});
