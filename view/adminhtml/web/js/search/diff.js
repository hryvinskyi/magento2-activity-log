/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What changed inside a value: a word-level diff of long text, and the items added to or removed from a JSON list
 *
 * Text splits into words, runs of whitespace and markup tags, and the longest common subsequence decides what stayed.
 * Each side is compared over its first MAX_TOKENS tokens at most, which bounds the work at MAX_TOKENS² steps; the
 * rest is returned as it is and flagged. Pure.
 */
define([], function () {
    'use strict';

    var MAX_TOKENS = 600,
        TOKEN = /(\s+|<[^>]+>)/;

    /**
     * @param {?string} text
     * @returns {Array<string>}
     */
    function tokens(text) {
        return String(text === null || text === undefined ? '' : text).split(TOKEN).filter(function (token) {
            return token !== '';
        });
    }

    /**
     * @param {Array<{op: string, text: string}>} parts
     * @param {string} op
     * @param {string} text
     */
    function append(parts, op, text) {
        var last = parts[parts.length - 1];

        if (last && last.op === op) {
            last.text += text;
        } else {
            parts.push({op: op, text: text});
        }
    }

    /**
     * @param {Array<string>} before
     * @param {Array<string>} after
     * @returns {Array<Uint16Array>} table[i][j]: length of the common subsequence of before[i…] and after[j…]
     */
    function lcsTable(before, after) {
        var table = [],
            i,
            j;

        for (i = 0; i <= before.length; i += 1) {
            table.push(new Uint16Array(after.length + 1));
        }
        for (i = before.length - 1; i >= 0; i -= 1) {
            for (j = after.length - 1; j >= 0; j -= 1) {
                table[i][j] = before[i] === after[j]
                    ? table[i + 1][j + 1] + 1
                    : Math.max(table[i + 1][j], table[i][j + 1]);
            }
        }

        return table;
    }

    /**
     * The word-level diff of two texts
     *
     * @param {?string} before
     * @param {?string} after
     * @returns {{parts: Array<{op: string, text: string}>, edits: number, capped: boolean,
     *     rest: {before: string, after: string}}} op "equal", "delete" or "insert", neighbours of one op joined;
     *     edits counts the parts that are not "equal"; rest holds what lies beyond the compared tokens
     */
    function words(before, after) {
        var allBefore = tokens(before),
            allAfter = tokens(after),
            left = allBefore.slice(0, MAX_TOKENS),
            right = allAfter.slice(0, MAX_TOKENS),
            table = lcsTable(left, right),
            parts = [],
            i = 0,
            j = 0;

        while (i < left.length && j < right.length) {
            if (left[i] === right[j]) {
                append(parts, 'equal', left[i]);
                i += 1;
                j += 1;
            } else if (table[i + 1][j] >= table[i][j + 1]) {
                append(parts, 'delete', left[i]);
                i += 1;
            } else {
                append(parts, 'insert', right[j]);
                j += 1;
            }
        }
        for (; i < left.length; i += 1) {
            append(parts, 'delete', left[i]);
        }
        for (; j < right.length; j += 1) {
            append(parts, 'insert', right[j]);
        }

        return {
            parts: parts,
            edits: parts.filter(function (part) {
                return part.op !== 'equal';
            }).length,
            capped: allBefore.length > MAX_TOKENS || allAfter.length > MAX_TOKENS,
            rest: {before: allBefore.slice(MAX_TOKENS).join(''), after: allAfter.slice(MAX_TOKENS).join('')}
        };
    }

    /**
     * @param {?string} text
     * @returns {?Array<*>} The list the text holds, an empty one for no value, or null when it holds no list
     */
    function listOf(text) {
        var value;

        if (text === null || text === undefined || text === '') {
            return [];
        }
        try {
            value = JSON.parse(text);
        } catch (notJson) {
            return null;
        }

        return Array.isArray(value) ? value : null;
    }

    /**
     * @param {*} item
     * @returns {string} A string item as it is, anything else as JSON
     */
    function shown(item) {
        return typeof item === 'string' ? item : JSON.stringify(item);
    }

    /**
     * The items one JSON list gained and lost against another; an item repeated n times counts n times
     *
     * @param {?string} before
     * @param {?string} after
     * @returns {{ok: boolean, added: Array<string>, removed: Array<string>, same: Array<string>}}
     *     ok false (and empty lists) when either side is not a JSON list
     */
    function array(before, after) {
        var left = listOf(before),
            right = listOf(after),
            unmatched = Object.create(null),
            removed = [],
            added = [],
            same = [];

        if (left === null || right === null) {
            return {ok: false, added: [], removed: [], same: []};
        }

        right.forEach(function (item) {
            var key = JSON.stringify(item);

            unmatched[key] = (unmatched[key] || 0) + 1;
        });
        left.forEach(function (item) {
            var key = JSON.stringify(item);

            if (unmatched[key] > 0) {
                unmatched[key] -= 1;
                same.push(shown(item));
            } else {
                removed.push(shown(item));
            }
        });
        right.forEach(function (item) {
            var key = JSON.stringify(item);

            if (unmatched[key] > 0) {
                unmatched[key] -= 1;
                added.push(shown(item));
            }
        });

        return {ok: true, added: added, removed: removed, same: same};
    }

    return Object.freeze({
        MAX_TOKENS: MAX_TOKENS,
        words: words,
        array: array
    });
});
