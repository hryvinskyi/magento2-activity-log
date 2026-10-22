/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The grid's pager, whose total reads "10,000+" when the server stopped counting at its bound: the grid then pages
 * through the counted records, and a narrower search shows the rest.
 */
define([
    'Magento_Ui/js/grid/paging/paging',
    'Hryvinskyi_ActivityLog/js/model/record-total'
], function (Paging, recordTotal) {
    'use strict';

    return Paging.extend({
        defaults: {
            totalTmpl: 'Hryvinskyi_ActivityLog/grid/paging-total',
            totalAtLeast: false,
            imports: {
                totalAtLeast: '${ $.provider }:data.totalAtLeast'
            }
        },

        /**
         * @returns {Object} Chainable.
         */
        initObservable: function () {
            this._super().track('totalAtLeast');

            return this;
        },

        /**
         * @returns {string}
         */
        totalText: function () {
            return recordTotal.label(this.totalRecords, this.totalAtLeast, function (value) {
                return value.toLocaleString();
            });
        }
    });
});
