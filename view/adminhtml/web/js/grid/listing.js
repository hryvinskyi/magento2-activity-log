/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The activity grid's rows, with the rows of one operation grouped under the activity that started it. A link to
 * the grid can ask for filters in its query string; they are applied as ordinary, removable filters.
 */
define([
    'Magento_Ui/js/grid/listing',
    'uiRegistry',
    'Hryvinskyi_ActivityLog/js/model/operation-groups',
    'Hryvinskyi_ActivityLog/js/model/url-filters',
    'Hryvinskyi_ActivityLog/js/grid/apply-filter'
], function (Listing, registry, operationGroups, urlFilters, applyFilter) {
    'use strict';

    return Listing.extend({
        defaults: {
            urlFilterFields: {},
            filtersName: '${ $.ns }.${ $.ns }.listing_top.listing_filters',
            listens: {
                rows: 'groupOperations'
            }
        },

        /**
         * @returns {Object} Chainable.
         */
        initialize: function () {
            this._super();
            this.applyUrlFilters(window.location.search);

            return this;
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
        },

        /**
         * @param {string} search
         */
        applyUrlFilters: function (search) {
            var values = urlFilters.read(search, Object.keys(this.urlFilterFields));

            if (Object.keys(values).length === 0) {
                return;
            }

            registry.get(this.filtersName, function (filters) {
                applyFilter(filters, values);
            });
        }
    });
});
