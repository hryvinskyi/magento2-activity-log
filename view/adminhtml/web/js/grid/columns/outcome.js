/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The outcome badge: an icon and a word, so it reads without colour.
 */
define([
    'Hryvinskyi_ActivityLog/js/grid/columns/activity-column'
], function (ActivityColumn) {
    'use strict';

    return ActivityColumn.extend({
        defaults: {
            bodyTmpl: 'Hryvinskyi_ActivityLog/grid/cells/outcome'
        },

        /**
         * @param {Object} row
         * @returns {string}
         */
        labelOf: function (row) {
            return this.outcomes[row.outcome] || String(row.outcome || '');
        },

        /**
         * @param {Object} row
         * @returns {string} success, failure or denied
         */
        outcomeOf: function (row) {
            return Object.prototype.hasOwnProperty.call(this.outcomes, row.outcome) ? row.outcome : 'failure';
        }
    });
});
