/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the grid's Field and Before → After cells show for one row, from the change columns the server fills
 * ("change_field", "change_field_code", "change_field_count", "change_kind", "change_before", "change_after")
 *
 * The field cell names the one changed field (with its code when that differs), or counts several and lists them.
 * The value cell shows the first changed field: short values inline, masked values only marked, long text as the
 * number of word edits, a JSON list as the items added and removed. A row's values are worked out once: the cell asks
 * for them several times while it draws, and a long text's diff is the costly part. Pure: the column holds the words.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/diff'
], function (diff) {
    'use strict';

    var KINDS = ['short', 'long', 'json', 'masked'],
        worked = new WeakMap();

    /**
     * @param {*} value
     * @returns {?string}
     */
    function text(value) {
        return typeof value === 'string' && value !== '' ? value : null;
    }

    /**
     * @param {Object} row
     * @returns {{count: number, label: ?string, code: ?string}} count 0 when the row records no field change
     */
    function field(row) {
        var count = Number(row.change_field_count);

        return {
            count: isFinite(count) && count > 0 ? Math.floor(count) : 0,
            label: text(row.change_field),
            code: text(row.change_field_code)
        };
    }

    /**
     * @param {Object} row
     * @returns {{kind: ?string, before: ?string, after: ?string, edits: number, added: number, removed: number,
     *     list: boolean}} kind null when the row records no value; edits for long text; added, removed and list
     *     (whether both sides are JSON lists) for JSON
     */
    function values(row) {
        var known = worked.get(row);

        if (known !== undefined && known.kind === row.change_kind && known.before === row.change_before
            && known.after === row.change_after) {
            return known.cell;
        }
        known = {kind: row.change_kind, before: row.change_before, after: row.change_after, cell: workOut(row)};
        worked.set(row, known);

        return known.cell;
    }

    /**
     * @param {Object} row
     * @returns {Object} As values() returns it
     */
    function workOut(row) {
        var kind = KINDS.indexOf(row.change_kind) === -1 ? null : row.change_kind,
            before = text(row.change_before),
            after = text(row.change_after),
            cell = {kind: kind, before: before, after: after, edits: 0, added: 0, removed: 0, list: false},
            lists;

        if (kind === 'long') {
            cell.edits = diff.words(before, after).edits;
        }
        if (kind === 'json') {
            lists = diff.array(before, after);
            cell.list = lists.ok;
            cell.added = lists.added.length;
            cell.removed = lists.removed.length;
        }

        return Object.freeze(cell);
    }

    return Object.freeze({
        field: field,
        values: values
    });
});
