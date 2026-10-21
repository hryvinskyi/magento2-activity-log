/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Adds filters to those already applied in a listing's filters component and applies them.
 */
define([], function () {
    'use strict';

    /**
     * @param {?Object} filters The listing's filters component
     * @param {Object} values Filter values by field
     */
    return function applyFilter(filters, values) {
        if (!filters) {
            return;
        }

        filters.setData(values, true);
        filters.apply();
    };
});
