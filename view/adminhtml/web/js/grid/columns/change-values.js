/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The first changed field's values: short ones inline as before → after, masked ones only marked, long text and JSON
 * lists summarised. The activity's detail page shows them whole.
 */
define([
    'Magento_Ui/js/grid/columns/column',
    'Hryvinskyi_ActivityLog/js/model/change-cell',
    'mage/translate'
], function (Column, changeCell, $t) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'Hryvinskyi_ActivityLog/grid/cells/change-values',
            sortable: false
        },

        /**
         * @param {Object} row
         * @returns {boolean}
         */
        isInline: function (row) {
            return changeCell.values(row).kind === 'short';
        },

        /**
         * @param {?string} value
         * @returns {string}
         */
        shown: function (value) {
            return value === null ? $t('(empty)') : value;
        },

        /**
         * @param {Object} row
         * @returns {string}
         */
        before: function (row) {
            return this.shown(changeCell.values(row).before);
        },

        /**
         * @param {Object} row
         * @returns {string}
         */
        after: function (row) {
            return this.shown(changeCell.values(row).after);
        },

        /**
         * @param {Object} row
         * @returns {boolean}
         */
        beforeIsEmpty: function (row) {
            return changeCell.values(row).before === null;
        },

        /**
         * @param {Object} row
         * @returns {boolean}
         */
        afterIsEmpty: function (row) {
            return changeCell.values(row).after === null;
        },

        /**
         * The summary of a value that is not shown inline, or an empty string
         *
         * @param {Object} row
         * @returns {string}
         */
        note: function (row) {
            var cell = changeCell.values(row),
                notes = {
                    masked: function () {
                        return $t('masked');
                    },
                    long: function () {
                        return (cell.edits === 1 ? $t('long text · %1 edit') : $t('long text · %1 edits'))
                            .replace('%1', String(cell.edits));
                    },
                    json: function () {
                        return cell.list
                            ? $t('JSON list · +%1 −%2').replace('%1', String(cell.added)).replace('%2', String(cell.removed))
                            : $t('JSON');
                    }
                };

            return notes[cell.kind] ? notes[cell.kind]() : '';
        }
    });
});
