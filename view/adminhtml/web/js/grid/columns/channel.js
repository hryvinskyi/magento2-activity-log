/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The channel badge; clicking it asks the search for that channel.
 */
define([
    'Hryvinskyi_ActivityLog/js/grid/columns/activity-column'
], function (ActivityColumn) {
    'use strict';

    return ActivityColumn.extend({
        defaults: {
            bodyTmpl: 'Hryvinskyi_ActivityLog/grid/cells/channel'
        },

        /**
         * @param {Object} row
         * @returns {string}
         */
        labelOf: function (row) {
            return this.channelOf(row.channel).label;
        },

        /**
         * @param {Object} row
         * @returns {Object}
         */
        badgeStyle: function (row) {
            var channel = this.channelOf(row.channel);

            return {backgroundColor: channel.bg, color: channel.fg};
        },

        /**
         * @param {Object} row
         * @param {Object} context The row as the click binding passes it
         * @param {Event} event
         */
        filterByChannel: function (row, context, event) {
            this.addCriterion(event, 'channel', row.channel);
        }
    });
});
