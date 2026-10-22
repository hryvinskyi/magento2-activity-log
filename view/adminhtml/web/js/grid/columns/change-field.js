/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The field an activity changed: its label and code, or how many fields and which.
 */
define([
    'Magento_Ui/js/grid/columns/column',
    'Hryvinskyi_ActivityLog/js/model/change-cell',
    'mage/translate'
], function (Column, changeCell, $t) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'Hryvinskyi_ActivityLog/grid/cells/change-field',
            sortable: false
        },

        /**
         * @param {Object} row
         * @returns {string}
         */
        title: function (row) {
            var field = changeCell.field(row);

            if (field.count > 1) {
                return $t('%1 fields').replace('%1', String(field.count));
            }

            return field.label || '—';
        },

        /**
         * The code of the one field, or the labels of several; empty when the title says it all
         *
         * @param {Object} row
         * @returns {string}
         */
        detail: function (row) {
            var field = changeCell.field(row);

            return field.count > 1 ? field.label || '' : field.code || '';
        },

        /**
         * @param {Object} row
         * @returns {boolean}
         */
        detailIsCode: function (row) {
            return changeCell.field(row).count <= 1;
        }
    });
});
