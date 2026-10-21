/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The rule that ties the rows of one operation together, in the colour of the channel that started it.
 */
define([
    'Hryvinskyi_ActivityLog/js/grid/columns/activity-column'
], function (ActivityColumn) {
    'use strict';

    return ActivityColumn.extend({
        defaults: {
            bodyTmpl: 'Hryvinskyi_ActivityLog/grid/cells/operation-rule'
        },

        /**
         * @param {Object} row
         * @returns {boolean}
         */
        inOperation: function (row) {
            return Boolean(row.operation);
        },

        /**
         * @param {Object} row
         * @returns {Object}
         */
        ruleCss: function (row) {
            var position = row.operation ? row.operation.position : '';

            return {
                'activity-operation-rule--first': position === 'first',
                'activity-operation-rule--middle': position === 'middle',
                'activity-operation-rule--last': position === 'last'
            };
        },

        /**
         * @param {Object} row
         * @returns {Object}
         */
        ruleStyle: function (row) {
            return {
                backgroundColor: row.operation ? this.channelOf(row.operation.triggerChannel).chart : 'transparent'
            };
        }
    });
});
