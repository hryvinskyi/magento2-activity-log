/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The action, clipped to one line; clicking a clipped action shows it whole.
 */
define([
    'Hryvinskyi_ActivityLog/js/grid/columns/activity-column'
], function (ActivityColumn) {
    'use strict';

    return ActivityColumn.extend({
        defaults: {
            bodyTmpl: 'Hryvinskyi_ActivityLog/grid/cells/action',
            expanded: {}
        },

        /**
         * @returns {Object} Chainable.
         */
        initObservable: function () {
            this._super().track('expanded');

            return this;
        },

        /**
         * @param {Object} row
         * @returns {boolean}
         */
        isExpanded: function (row) {
            return Boolean(this.expanded[row.entity_id]);
        },

        /**
         * @param {Object} row
         */
        toggle: function (row) {
            var expanded = Object.assign({}, this.expanded);

            expanded[row.entity_id] = !expanded[row.entity_id];
            this.expanded = expanded;
        }
    });
});
