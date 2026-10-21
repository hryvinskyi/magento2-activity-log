/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * When an activity happened, in the store timezone; rows inside an operation also show their offset from its start.
 */
define([
    'Hryvinskyi_ActivityLog/js/grid/columns/activity-column',
    'mage/translate',
    'moment',
    'moment-timezone-with-data'
], function (ActivityColumn, $t, moment) {
    'use strict';

    return ActivityColumn.extend({
        defaults: {
            bodyTmpl: 'Hryvinskyi_ActivityLog/grid/cells/time'
        },

        /**
         * @param {Object} row
         * @returns {?Object} A moment in the store timezone, or null for a row without a time
         */
        momentOf: function (row) {
            var ms = Number(row.occurred_at_ms);

            if (!isFinite(ms) || ms <= 0) {
                return null;
            }

            return moment.tz.zone(this.timezone) ? moment(ms).tz(this.timezone) : moment(ms);
        },

        /**
         * @param {Object} row
         * @returns {string}
         */
        dateText: function (row) {
            var time = this.momentOf(row);

            return time ? time.format('ll') : '';
        },

        /**
         * @param {Object} row
         * @returns {string}
         */
        clockText: function (row) {
            var time = this.momentOf(row);

            return time ? time.format('LTS') : '';
        },

        /**
         * @param {Object} row
         * @returns {string}
         */
        relativeText: function (row) {
            var time = this.momentOf(row);

            return time ? time.fromNow() : '';
        },

        /**
         * @param {Object} row
         * @returns {string} Empty for a row that starts its operation or stands alone
         */
        offsetText: function (row) {
            if (!row.operation || row.operation.position === 'first') {
                return '';
            }

            return $t('+%1 ms').replace('%1', row.operation.offsetMs.toLocaleString());
        }
    });
});
