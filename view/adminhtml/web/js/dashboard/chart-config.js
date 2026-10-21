/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Chart.js configuration for a line or bar widget, in the admin's look. Pure: builds the object, draws nothing.
 */
define([], function () {
    'use strict';

    var GRID = '#e3e3e3',
        TEXT = '#514943',
        LABEL_MAX = 26;

    /**
     * @param {string} label
     * @returns {string}
     */
    function clip(label) {
        return label.length > LABEL_MAX ? label.slice(0, LABEL_MAX - 1) + '…' : label;
    }

    /**
     * @param {number} value
     * @param {string} unit
     * @returns {string}
     */
    function format(value, unit) {
        var text = Number(value).toLocaleString();

        return unit ? text + ' ' + unit : text;
    }

    /**
     * @param {{labels: Array<string>, datasets: Array<Object>, stacked: ?boolean, horizontal: ?boolean,
     *     unit: ?string}} data
     * @param {string} viewType "line" or "bar"
     * @param {{total: string}} wording
     * @returns {Object}
     */
    function build(data, viewType, wording) {
        var line = viewType === 'line',
            horizontal = Boolean(data.horizontal),
            stacked = Boolean(data.stacked),
            unit = data.unit || '',
            valueAxis = horizontal ? 'x' : 'y',
            categoryAxis = horizontal ? 'y' : 'x',
            scales = {};

        scales[categoryAxis] = {
            stacked: stacked,
            grid: {display: false},
            ticks: {
                color: TEXT,
                maxRotation: 0,
                autoSkip: !horizontal,
                maxTicksLimit: horizontal ? undefined : 10,
                /**
                 * @param {number} value
                 * @returns {string}
                 */
                callback: function (value) {
                    return clip(String(this.getLabelForValue(value)));
                }
            }
        };
        scales[valueAxis] = {
            stacked: stacked,
            beginAtZero: true,
            grid: {color: GRID},
            border: {display: false},
            ticks: {
                color: TEXT,
                precision: 0,
                maxTicksLimit: 6,
                /**
                 * @param {number} value
                 * @returns {string}
                 */
                callback: function (value) {
                    return format(value, unit);
                }
            }
        };

        return {
            type: line ? 'line' : 'bar',
            data: {
                labels: data.labels,
                datasets: data.datasets.map(function (dataset) {
                    return {
                        label: dataset.label,
                        data: dataset.values,
                        backgroundColor: dataset.color,
                        borderColor: dataset.color,
                        borderWidth: line ? 2 : 0,
                        pointRadius: 0,
                        tension: 0.3,
                        fill: line && stacked,
                        stack: stacked ? 'activity' : undefined,
                        barThickness: horizontal ? 14 : undefined
                    };
                })
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                indexAxis: horizontal ? 'y' : 'x',
                interaction: {mode: 'index', intersect: false},
                scales: scales,
                plugins: {
                    legend: {
                        display: data.datasets.length > 1,
                        position: 'bottom',
                        labels: {color: TEXT, boxWidth: 10, boxHeight: 10}
                    },
                    tooltip: {
                        callbacks: {
                            /**
                             * @param {Object} item
                             * @returns {string}
                             */
                            label: function (item) {
                                var value = horizontal ? item.parsed.x : item.parsed.y;

                                return (data.datasets.length > 1 ? item.dataset.label + ': ' : '') + format(value, unit);
                            },
                            /**
                             * @param {Array<Object>} items
                             * @returns {string}
                             */
                            footer: function (items) {
                                var total;

                                if (!stacked || items.length < 2) {
                                    return '';
                                }

                                total = items.reduce(function (sum, item) {
                                    return sum + (horizontal ? item.parsed.x : item.parsed.y);
                                }, 0);

                                return wording.total + ' ' + format(total, unit);
                            }
                        }
                    }
                }
            }
        };
    }

    return Object.freeze({build: build});
});
