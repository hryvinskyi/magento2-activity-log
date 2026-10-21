/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * "View" opens the activity; "Show related" filters the grid to its operation.
 */
define([
    'Magento_Ui/js/grid/columns/column',
    'Hryvinskyi_ActivityLog/js/grid/apply-filter'
], function (Column, applyFilter) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'Hryvinskyi_ActivityLog/grid/cells/activity-actions',
            sortable: false,
            modules: {
                filters: '${ $.ns }.${ $.ns }.listing_top.listing_filters'
            }
        },

        /**
         * @param {Object} row
         */
        showRelated: function (row) {
            applyFilter(this.filters(), {correlation_id: row.correlation_id});
        }
    });
});
