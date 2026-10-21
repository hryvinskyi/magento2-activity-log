/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Draws line and bar data with Chart.js, or as its table when the widget's toggle asks for it.
 */
define([
    'chartJs',
    'Hryvinskyi_ActivityLog/js/dashboard/chart-config',
    'Hryvinskyi_ActivityLog/js/dashboard/chart-table',
    'Hryvinskyi_ActivityLog/js/dashboard/renderers/table'
], function (Chart, chartConfig, chartTable, tableRenderer) {
    'use strict';

    /**
     * @param {string} viewType "line" or "bar"
     * @returns {{render: Function, isEmpty: Function}}
     */
    return function chartRenderer(viewType) {
        return Object.freeze({
            /**
             * @param {HTMLElement} body
             * @param {Object} data
             * @param {{asTable: boolean, wording: Object, title: string}} options
             * @returns {{destroy: Function}}
             */
            render: function (body, data, options) {
                var holder, canvas, chart, rows;

                if (options.asTable) {
                    return tableRenderer.render(body, chartTable.toTable(data, options.wording));
                }

                holder = document.createElement('div');
                canvas = document.createElement('canvas');
                rows = data.horizontal ? Math.max(data.labels.length, 4) : 0;
                holder.className = 'activity-widget-chart';
                holder.style.height = data.horizontal ? (rows * 28 + 40) + 'px' : '';
                canvas.setAttribute('role', 'img');
                canvas.setAttribute('aria-label', options.title);
                holder.appendChild(canvas);
                body.appendChild(holder);
                chart = new Chart(canvas, chartConfig.build(data, viewType, options.wording));

                return {
                    /** Releases the chart and its canvas. */
                    destroy: function () {
                        chart.destroy();
                        holder.remove();
                    }
                };
            },

            /**
             * @param {Object} data
             * @returns {boolean}
             */
            isEmpty: function (data) {
                return !Array.isArray(data.datasets) || data.datasets.length === 0 ||
                    !Array.isArray(data.labels) || data.labels.length === 0;
            }
        });
    };
});
