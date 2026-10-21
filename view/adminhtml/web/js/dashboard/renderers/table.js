/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Draws table data: {columns, rows}; a row with an href links its first cell.
 */
define([], function () {
    'use strict';

    /**
     * @param {HTMLElement} body
     * @param {{columns: Array<Object>, rows: Array<Object>}} data
     * @returns {{destroy: Function}}
     */
    function render(body, data) {
        var wrap = document.createElement('div'),
            table = document.createElement('table'),
            head = table.createTHead().insertRow(),
            rows = table.createTBody();

        wrap.className = 'activity-widget-table';
        data.columns.forEach(function (column) {
            var cell = document.createElement('th');

            cell.textContent = column.label;
            cell.className = column.align === 'right' ? 'activity-widget-table__number' : '';
            head.appendChild(cell);
        });

        data.rows.forEach(function (row) {
            var line = rows.insertRow();

            data.columns.forEach(function (column, index) {
                var cell = line.insertCell(),
                    text = String(row.cells[column.key] === undefined ? '' : row.cells[column.key]),
                    link;

                cell.className = column.align === 'right' ? 'activity-widget-table__number' : '';

                if (index === 0 && row.swatch) {
                    link = document.createElement('span');
                    link.className = 'activity-widget-table__swatch';
                    link.style.background = row.swatch;
                    cell.appendChild(link);
                }

                if (index === 0 && typeof row.href === 'string') {
                    link = document.createElement('a');
                    link.href = row.href;
                    link.textContent = text;
                    cell.appendChild(link);
                } else {
                    cell.appendChild(document.createTextNode(text));
                }
            });
        });

        wrap.appendChild(table);
        body.appendChild(wrap);

        return {
            /** Removes the table. */
            destroy: function () {
                wrap.remove();
            }
        };
    }

    /**
     * @param {Object} data
     * @returns {boolean}
     */
    function isEmpty(data) {
        return !Array.isArray(data.rows) || data.rows.length === 0;
    }

    return Object.freeze({render: render, isEmpty: isEmpty});
});
