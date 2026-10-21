/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The duration on the duration scale: plain when good, a dot when slow, a dot and bold when critical, a dash for an
 * activity without a duration of its own.
 */
define([
    'Hryvinskyi_ActivityLog/js/grid/columns/activity-column',
    'Hryvinskyi_ActivityLog/js/model/duration-scale',
    'mage/translate'
], function (ActivityColumn, durationScale, $t) {
    'use strict';

    return ActivityColumn.extend({
        defaults: {
            bodyTmpl: 'Hryvinskyi_ActivityLog/grid/cells/duration'
        },

        /**
         * @param {Object} row
         * @returns {?Object}
         */
        levelOf: function (row) {
            return durationScale.levelOf(row.duration_ms, this.durationScale);
        },

        /**
         * @param {Object} row
         * @returns {string}
         */
        text: function (row) {
            return this.levelOf(row) ?
                $t('%1 ms').replace('%1', Math.round(Number(row.duration_ms)).toLocaleString()) :
                '—';
        },

        /**
         * @param {Object} row
         * @returns {string}
         */
        title: function (row) {
            var level = this.levelOf(row);

            return level ? level.label : $t('Part of the parent call');
        },

        /**
         * @param {Object} row
         * @returns {boolean}
         */
        showsDot: function (row) {
            var level = this.levelOf(row);

            return Boolean(level) && level.key !== 'good';
        },

        /**
         * @param {Object} row
         * @returns {Object}
         */
        dotStyle: function (row) {
            var level = this.levelOf(row);

            return {backgroundColor: level ? level.color : 'transparent'};
        },

        /**
         * @param {Object} row
         * @returns {Object}
         */
        textStyle: function (row) {
            var level = this.levelOf(row);

            return {
                color: level && level.key !== 'good' ? level.text : '',
                fontWeight: level && level.key === 'critical' ? '600' : ''
            };
        }
    });
});
