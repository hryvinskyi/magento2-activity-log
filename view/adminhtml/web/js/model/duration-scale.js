/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Places a duration on the duration scale. The scale itself (thresholds, colours) comes from the server, which
 * uses the same scale for what it renders; this module only reads it. Pure: no DOM, no wording.
 */
define([], function () {
    'use strict';

    /**
     * @param {*} value Milliseconds, as a number or numeric text
     * @param {Array<{key: string, below: ?number, color: string, text: string}>} scale Levels in rising order;
     *     the last level has no upper bound (below null)
     * @returns {?{key: string, below: ?number, color: string, text: string}} Null when there is no duration
     */
    function levelOf(value, scale) {
        var ms;

        if (value === null || value === undefined || value === '' || !Array.isArray(scale)) {
            return null;
        }

        ms = Number(value);

        if (!isFinite(ms) || ms < 0) {
            return null;
        }

        return scale.find(function (level) {
            return level.below === null || ms < level.below;
        }) || null;
    }

    return Object.freeze({levelOf: levelOf});
});
