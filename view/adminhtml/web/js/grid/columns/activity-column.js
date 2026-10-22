/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Base of the activity grid's columns: the channel, outcome and duration vocabulary the server hands every
 * column, and asking the page's search for a filter from a cell.
 */
define([
    'Magento_Ui/js/grid/columns/column',
    'Hryvinskyi_ActivityLog/js/grid/announce-criterion'
], function (Column, announceCriterion) {
    'use strict';

    var UNKNOWN_CHANNEL = Object.freeze({chart: '#8C8C8C', bg: '#ECECEC', fg: '#4D4D4D'});

    return Column.extend({
        defaults: {
            channels: {},
            outcomes: {},
            durationScale: [],
            timezone: 'UTC'
        },

        /**
         * A registered channel, or a grey stand-in labelled with the raw code for a channel no module registers.
         *
         * @param {string} code
         * @returns {{label: string, chart: string, bg: string, fg: string}}
         */
        channelOf: function (code) {
            var channel = this.channels[code];

            return channel || Object.assign({label: String(code || '')}, UNKNOWN_CHANNEL);
        },

        /**
         * Asks the page's search for a filter, from the user's event on the cell
         *
         * @param {?Event} event
         * @param {string} key A search key
         * @param {*} value
         * @param {boolean=} alone True to ask for this filter alone, replacing the others
         */
        addCriterion: function (event, key, value, alone) {
            announceCriterion(event, key, value, alone);
        }
    });
});
