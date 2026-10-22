/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Asks the page's search for a filter from an element of the grid — one more, or that filter alone — through the
 * bubbling event that search/criterion-event describes. A value the search would not take is not announced.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/criterion-event'
], function (criterionEvent) {
    'use strict';

    /**
     * @param {?Event} event The user's event on the element offering the filter
     * @param {string} key
     * @param {*} value
     * @param {boolean=} alone True to ask for this filter alone
     * @returns {boolean} Whether the request was announced
     */
    return function announceCriterion(event, key, value, alone) {
        var detail = criterionEvent.detail(key, value, alone),
            target = event ? event.currentTarget || event.target : null;

        if (!detail || !target || typeof target.dispatchEvent !== 'function') {
            return false;
        }

        target.dispatchEvent(new CustomEvent(criterionEvent.NAME, {bubbles: true, detail: detail}));

        return true;
    };
});
