/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The grid's row selection, for the search the grid shows
 *
 * A mass action sends the selection with the grid's search ("activity_search"), so "all" means every activity of
 * that search and nothing else, and a new search clears the selection. While the grid only knows that more than its
 * counted total match, "Select All" is not offered: nobody could see how many activities it would take. The bound is
 * observed and read before anything else, so the actions offered follow it as the count changes.
 */
define([
    'Magento_Ui/js/grid/columns/multiselect',
    'Hryvinskyi_ActivityLog/js/model/selection-actions'
], function (Multiselect, selectionActions) {
    'use strict';

    var SEARCH_PARAM = 'activity_search';

    return Multiselect.extend({
        defaults: {
            totalAtLeast: false,
            imports: {
                totalAtLeast: '${ $.provider }:data.totalAtLeast'
            },
            listens: {
                '${ $.provider }:params.activity_search': 'onFilter'
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
         * The provider's filtering, with the grid's search
         *
         * @returns {Object}
         */
        getFiltering: function () {
            var filtering = this._super(),
                source = this.source(),
                search = source ? source.get('params.' + SEARCH_PARAM) : undefined;

            if (search !== undefined) {
                filtering[SEARCH_PARAM] = search;
            }

            return filtering;
        },

        /**
         * @param {string} actionId
         * @returns {boolean}
         */
        isActionRelevant: function (actionId) {
            var totalAtLeast = this.totalAtLeast;

            return selectionActions.allows(actionId, totalAtLeast) && this._super(actionId);
        }
    });
});
