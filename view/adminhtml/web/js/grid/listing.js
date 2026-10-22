/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The activity grid's rows, with the rows of one operation grouped under the activity that started it. What the
 * grid shows is the search its provider is given (the "activity_search" parameter), never filters of its own.
 */
define([
    'Magento_Ui/js/grid/listing',
    'Hryvinskyi_ActivityLog/js/model/operation-groups'
], function (Listing, operationGroups) {
    'use strict';

    return Listing.extend({
        defaults: {
            listens: {
                rows: 'groupOperations'
            }
        },

        /**
         * Replaces the rows the provider delivered with their grouped copy, once per delivery.
         *
         * @param {Array<Object>} rows
         */
        groupOperations: function (rows) {
            var grouped;

            if (!Array.isArray(rows) || rows.operationsGrouped) {
                return;
            }

            grouped = operationGroups.arrange(rows);
            grouped.operationsGrouped = true;
            this.rows = grouped;
        }
    });
});
