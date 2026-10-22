/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * "View" opens the activity; "Show related" asks the search for its operation alone: every activity of it.
 */
define([
    'Magento_Ui/js/grid/columns/column',
    'Hryvinskyi_ActivityLog/js/grid/announce-criterion'
], function (Column, announceCriterion) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'Hryvinskyi_ActivityLog/grid/cells/activity-actions',
            sortable: false
        },

        /**
         * @param {Object} row
         * @param {Object} context The row as the click binding passes it
         * @param {Event} event
         */
        showRelated: function (row, context, event) {
            announceCriterion(event, 'request', row.correlation_id, true);
        }
    });
});
