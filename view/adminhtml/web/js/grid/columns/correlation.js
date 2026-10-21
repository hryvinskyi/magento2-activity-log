/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The first six characters of the correlation id; clicking copies the whole id. "×N" shows how many activities
 * share it and filters the grid to them.
 */
define([
    'Hryvinskyi_ActivityLog/js/grid/columns/activity-column'
], function (ActivityColumn) {
    'use strict';

    var COPIED_FOR_MS = 1500;

    return ActivityColumn.extend({
        defaults: {
            bodyTmpl: 'Hryvinskyi_ActivityLog/grid/cells/correlation',
            copiedId: null
        },

        /**
         * @returns {Object} Chainable.
         */
        initObservable: function () {
            this._super().track('copiedId');

            return this;
        },

        /**
         * @param {Object} row
         * @returns {string}
         */
        shortId: function (row) {
            return String(row.correlation_id || '').slice(0, 6);
        },

        /**
         * @param {Object} row
         * @returns {number}
         */
        operationSize: function (row) {
            var size = Number(row.operation_size);

            return isFinite(size) ? size : 1;
        },

        /**
         * @param {Object} row
         * @returns {boolean}
         */
        isCopied: function (row) {
            return this.copiedId === row.entity_id;
        },

        /**
         * @param {Object} row
         */
        copy: function (row) {
            var self = this;

            if (!navigator.clipboard) {
                return;
            }

            navigator.clipboard.writeText(String(row.correlation_id)).then(function () {
                self.copiedId = row.entity_id;
                window.setTimeout(function () {
                    if (self.copiedId === row.entity_id) {
                        self.copiedId = null;
                    }
                }, COPIED_FOR_MS);
            }, function () {
                self.copiedId = null;
            });
        },

        /**
         * @param {Object} row
         */
        showOperation: function (row) {
            this.filterBy({correlation_id: row.correlation_id});
        }
    });
});
