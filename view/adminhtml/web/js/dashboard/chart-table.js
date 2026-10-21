/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The table a chart's data reads as: one row per label, one column per series, and a total column when the series
 * are stacked. It is the chart's text equivalent. Pure.
 */
define([], function () {
    'use strict';

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
     * @param {{labels: Array<string>, datasets: Array<Object>, stacked: ?boolean, unit: ?string}} data
     * @param {{label: string, total: string}} wording
     * @returns {{columns: Array<Object>, rows: Array<Object>}}
     */
    function toTable(data, wording) {
        var unit = data.unit || '',
            columns = [{key: 'label', label: wording.label}];

        data.datasets.forEach(function (dataset, index) {
            columns.push({key: 'series' + index, label: dataset.label, align: 'right'});
        });

        if (data.stacked && data.datasets.length > 1) {
            columns.push({key: 'total', label: wording.total, align: 'right'});
        }

        return {
            columns: columns,
            rows: data.labels.map(function (label, row) {
                var cells = {label: label},
                    total = 0;

                data.datasets.forEach(function (dataset, index) {
                    var value = Number(dataset.values[row]) || 0;

                    cells['series' + index] = format(value, unit);
                    total += value;
                });

                if (data.stacked && data.datasets.length > 1) {
                    cells.total = format(total, unit);
                }

                return {cells: cells};
            })
        };
    }

    return Object.freeze({toTable: toTable});
});
