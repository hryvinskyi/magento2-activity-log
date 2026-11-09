/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Two texts side by side, line against line: the rows of a split diff, with long unchanged stretches folded
 *
 * A row has a before side and an after side. Between two unchanged lines, the k-th removed line sits beside the k-th
 * added line, and a line with no partner sits beside nothing (null). Inside such a pair the parts that changed are
 * marked, when the two lines still share enough to make that worth reading: the unchanged parts hold at least 30 % of
 * the non-whitespace characters of the longer line. Lines that share less are only marked as a whole, and so are the
 * pairs past the first MAX_HIGHLIGHTED_PAIRS of a value, which bounds the work. Every change keeps CONTEXT unchanged
 * rows visible on each side; a longer unchanged stretch is folded when that hides at least two rows. Pure: no
 * wording, the renderer brings it.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/diff'
], function (diff) {
    'use strict';

    var CONTEXT = 3,
        MAX_HIGHLIGHTED_PAIRS = 200,
        SHARED_SHARE_PERCENT = 30,
        SMALLEST_FOLD = 2;

    /**
     * @param {string} text
     * @returns {number} The characters (code points) of the text that are not whitespace
     */
    function solidLength(text) {
        return (text.match(/\S/gu) || []).length;
    }

    /**
     * @param {number} number
     * @param {string} op
     * @param {string} text
     * @returns {{number: number, op: string, parts: Array<{text: string, changed: boolean}>}} A side with no marked part
     */
    function wholeSide(number, op, text) {
        return {number: number, op: op, parts: [{text: text, changed: false}]};
    }

    /**
     * @param {Array<{text: string, changed: boolean}>} parts
     * @param {string} text
     * @param {boolean} changed
     */
    function append(parts, text, changed) {
        var last = parts[parts.length - 1];

        if (last && last.changed === changed) {
            last.text += text;
        } else {
            parts.push({text: text, changed: changed});
        }
    }

    /**
     * The parts of a removed line and of the added line beside it, marked where they differ
     *
     * @param {string} before
     * @param {string} after
     * @returns {?{before: Array<{text: string, changed: boolean}>, after: Array<{text: string, changed: boolean}>,
     *     capped: boolean}} null when the lines share too little for their differences to be worth marking
     */
    function highlight(before, after) {
        var longest = Math.max(solidLength(before), solidLength(after)),
            compared,
            shared,
            sides = {before: [], after: []};

        if (longest === 0) {
            return null;
        }
        compared = diff.inline(before, after);
        shared = compared.parts.reduce(function (sum, part) {
            return part.op === 'equal' ? sum + solidLength(part.text) : sum;
        }, 0);
        if (shared * 100 < longest * SHARED_SHARE_PERCENT) {
            return null;
        }
        compared.parts.forEach(function (part) {
            if (part.op !== 'insert') {
                append(sides.before, part.text, part.op === 'delete');
            }
            if (part.op !== 'delete') {
                append(sides.after, part.text, part.op === 'insert');
            }
        });

        return {before: sides.before, after: sides.after, capped: compared.capped};
    }

    /**
     * Lays out the lines of one value as rows, numbering each side and pairing removed lines with added ones
     *
     * @param {Array<{op: string, text: string}>} lines as diff.lines() returns them
     * @returns {{rows: Array<Object>, capped: boolean}} rows of type "line"; capped when a pair's comparison was
     */
    function layOut(lines) {
        var rows = [],
            numbers = {before: 0, after: 0},
            deleted = [],
            inserted = [],
            pairs = 0,
            capped = false,
            take;

        /**
         * @param {string} side "before" or "after"
         * @returns {number} The next line number on that side
         */
        function next(side) {
            numbers[side] += 1;

            return numbers[side];
        }

        /**
         * Turns the changed lines gathered since the last unchanged one into rows
         */
        function flush() {
            var count = Math.max(deleted.length, inserted.length),
                index,
                left,
                right,
                marked;

            for (index = 0; index < count; index += 1) {
                left = index < deleted.length ? wholeSide(next('before'), 'delete', deleted[index]) : null;
                right = index < inserted.length ? wholeSide(next('after'), 'insert', inserted[index]) : null;
                if (left !== null && right !== null) {
                    pairs += 1;
                    marked = pairs <= MAX_HIGHLIGHTED_PAIRS ? highlight(deleted[index], inserted[index]) : null;
                    if (marked !== null) {
                        left.parts = marked.before;
                        right.parts = marked.after;
                        capped = capped || marked.capped;
                    }
                }
                rows.push({type: 'line', before: left, after: right});
            }
            deleted = [];
            inserted = [];
        }

        take = {
            'equal': function (text) {
                flush();
                rows.push({
                    type: 'line',
                    before: wholeSide(next('before'), 'equal', text),
                    after: wholeSide(next('after'), 'equal', text)
                });
            },
            'delete': function (text) {
                deleted.push(text);
            },
            'insert': function (text) {
                inserted.push(text);
            }
        };
        lines.forEach(function (line) {
            take[line.op](line.text);
        });
        flush();

        return {rows: rows, capped: capped};
    }

    /**
     * @param {{before: ?Object, after: ?Object}} row
     * @returns {boolean} Whether the row holds a change
     */
    function isChanged(row) {
        return row.before === null || row.after === null || row.before.op !== 'equal';
    }

    /**
     * Folds the unchanged stretches that lie further than CONTEXT rows from every change
     *
     * @param {Array<Object>} rows rows of type "line"
     * @returns {Array<Object>} the rows, with each stretch of at least two hidden rows replaced by one fold
     */
    function fold(rows) {
        var shown = rows.map(isChanged),
            result = [],
            hidden = [];

        rows.forEach(function (row, index) {
            var from,
                to,
                at;

            if (!isChanged(row)) {
                return;
            }
            from = Math.max(0, index - CONTEXT);
            to = Math.min(rows.length - 1, index + CONTEXT);
            for (at = from; at <= to; at += 1) {
                shown[at] = true;
            }
        });

        /**
         * Writes out the rows hidden since the last shown one: as a fold when there are enough of them
         */
        function flush() {
            if (hidden.length >= SMALLEST_FOLD) {
                result.push({type: 'fold', rows: hidden});
            } else {
                hidden.forEach(function (row) {
                    result.push(row);
                });
            }
            hidden = [];
        }

        rows.forEach(function (row, index) {
            if (shown[index]) {
                flush();
                result.push(row);
            } else {
                hidden.push(row);
            }
        });
        flush();

        return result;
    }

    /**
     * @param {Array<Object>} rows rows of type "line"
     * @returns {number} The runs of consecutive changed rows
     */
    function changeRuns(rows) {
        return rows.reduce(function (runs, row, index) {
            return isChanged(row) && (index === 0 || !isChanged(rows[index - 1])) ? runs + 1 : runs;
        }, 0);
    }

    /**
     * The split diff of two texts
     *
     * @param {?string} before
     * @param {?string} after
     * @returns {{rows: Array<{type: string, before: ?Object, after: ?Object}|{type: string, rows: Array<Object>}>,
     *     changes: number, capped: boolean}} rows: {type: "line", before, after}, where a side is
     *     {number (from 1 on its own side), op ("equal", "delete" or "insert"), parts: [{text, changed}]} or null
     *     beside a line with no partner, and {type: "fold", rows} for a folded stretch of unchanged rows; changes
     *     counts the runs of changed rows; capped when the lines or a marked pair were too long to compare in full
     */
    function split(before, after) {
        var compared = diff.lines(before, after),
            laidOut = layOut(compared.lines);

        return {
            rows: fold(laidOut.rows),
            changes: changeRuns(laidOut.rows),
            capped: compared.capped || laidOut.capped
        };
    }

    return Object.freeze({
        CONTEXT: CONTEXT,
        MAX_HIGHLIGHTED_PAIRS: MAX_HIGHLIGHTED_PAIRS,
        split: split
    });
});
