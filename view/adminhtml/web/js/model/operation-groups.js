/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Groups the rows of one operation shown next to each other.
 *
 * Rows sharing a correlation id that sit together on the page are put in chronological order under the activity
 * that started the operation, and each gets an "operation" note: its position in the group, the group size, the
 * channel that started it and its offset from the start. A row standing alone gets null. Pure: no DOM, no Knockout.
 */
define([], function () {
    'use strict';

    /**
     * @param {Object} row
     * @returns {string}
     */
    function correlationOf(row) {
        return typeof row.correlation_id === 'string' ? row.correlation_id : '';
    }

    /**
     * @param {Object} row
     * @param {string} field
     * @returns {number}
     */
    function numberOf(row, field) {
        var value = Number(row[field]);

        return isFinite(value) ? value : 0;
    }

    /**
     * @param {Object} a
     * @param {Object} b
     * @returns {number}
     */
    function chronological(a, b) {
        return numberOf(a, 'occurred_at_ms') - numberOf(b, 'occurred_at_ms') ||
            numberOf(a, 'entity_id') - numberOf(b, 'entity_id');
    }

    /**
     * @param {number} index
     * @param {number} size
     * @returns {string}
     */
    function positionOf(index, size) {
        if (index === 0) {
            return 'first';
        }

        return index === size - 1 ? 'last' : 'middle';
    }

    /**
     * @param {Array<Object>} run
     * @returns {Array<Object>}
     */
    function annotate(run) {
        var ordered = run.slice().sort(chronological),
            start = numberOf(ordered[0], 'occurred_at_ms'),
            trigger = String(ordered[0].channel || '');

        return ordered.map(function (row, index) {
            return Object.assign({}, row, {
                operation: {
                    position: positionOf(index, ordered.length),
                    size: ordered.length,
                    triggerChannel: trigger,
                    offsetMs: numberOf(row, 'occurred_at_ms') - start
                }
            });
        });
    }

    /**
     * @param {Array<Object>} rows Rows in the order the grid received them
     * @returns {Array<Object>} New rows; the input is not changed
     */
    function arrange(rows) {
        var arranged = [],
            start = 0,
            end;

        while (start < rows.length) {
            end = start + 1;

            while (end < rows.length && correlationOf(rows[start]) !== '' &&
                correlationOf(rows[end]) === correlationOf(rows[start])) {
                end++;
            }

            if (end - start > 1) {
                arranged.push.apply(arranged, annotate(rows.slice(start, end)));
            } else {
                arranged.push(Object.assign({}, rows[start], {operation: null}));
            }

            start = end;
        }

        return arranged;
    }

    return Object.freeze({arrange: arrange});
});
