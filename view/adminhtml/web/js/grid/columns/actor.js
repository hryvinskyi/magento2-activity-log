/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Who an activity is attributed to: the account, and for a delegated action the client that acted for it.
 * Clicking the name asks the search for that actor.
 */
define([
    'Hryvinskyi_ActivityLog/js/grid/columns/activity-column',
    'mage/translate'
], function (ActivityColumn, $t) {
    'use strict';

    var TYPE_LABELS = Object.freeze({
        admin: $t('admin'),
        integration: $t('integration'),
        customer: $t('customer'),
        guest: $t('guest'),
        cli: $t('command line'),
        cron: $t('cron'),
        anonymous: $t('anonymous')
    });

    return ActivityColumn.extend({
        defaults: {
            bodyTmpl: 'Hryvinskyi_ActivityLog/grid/cells/actor'
        },

        /**
         * The kind of account, shown when it is not the obvious one: an admin acting in the admin, or nobody
         *
         * @param {Object} row
         * @returns {boolean}
         */
        showsType: function (row) {
            return !this.isDelegated(row) && row.actor_type !== 'admin' && row.actor_type !== 'anonymous';
        },

        /**
         * @param {Object} row
         * @returns {string}
         */
        typeLabel: function (row) {
            return TYPE_LABELS[row.actor_type] || String(row.actor_type || '');
        },

        /**
         * @param {Object} row
         * @returns {boolean}
         */
        isDelegated: function (row) {
            return typeof row.via_client === 'string' && row.via_client !== '';
        },

        /**
         * @param {Object} row
         * @returns {boolean}
         */
        hasViaChannel: function (row) {
            return typeof row.via_channel === 'string' && row.via_channel !== '';
        },

        /**
         * @param {Object} row
         * @returns {string}
         */
        viaChannelLabel: function (row) {
            return this.channelOf(row.via_channel).label;
        },

        /**
         * @param {Object} row
         * @returns {Object}
         */
        viaChannelStyle: function (row) {
            var channel = this.channelOf(row.via_channel);

            return {backgroundColor: channel.bg, color: channel.fg};
        },

        /**
         * @param {Object} row
         * @param {Object} context The row as the click binding passes it
         * @param {Event} event
         */
        filterByActor: function (row, context, event) {
            this.addCriterion(event, 'by', row.actor_name);
        }
    });
});
