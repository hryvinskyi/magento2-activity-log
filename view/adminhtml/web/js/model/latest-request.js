/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Of requests started one after another, which one is still wanted: the latest started. An answer to an earlier one
 * arrives too late to be shown, whenever it arrives. Pure.
 */
define([], function () {
    'use strict';

    /**
     * @returns {{start: function(): function(): boolean}} start() marks a request started and returns whether that
     *     request is still the latest
     */
    function create() {
        var started = 0;

        return Object.freeze({
            start: function () {
                var mine;

                started += 1;
                mine = started;

                return function isLatest() {
                    return mine === started;
                };
            }
        });
    }

    return Object.freeze({create: create});
});
