/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * How many records a grid says it found: the count, and "10,000+" when the server stopped counting there. Pure: the
 * caller passes how numbers are written.
 */
define([], function () {
    'use strict';

    /**
     * @param {*} count Records counted
     * @param {*} atLeast Whether more records match than were counted
     * @param {function(number): string} format Writes a number for the reader
     * @returns {string}
     */
    function label(count, atLeast, format) {
        var value = Number(count);

        if (!isFinite(value) || value < 0) {
            value = 0;
        }

        return format(Math.floor(value)) + (atLeast === true ? '+' : '');
    }

    return Object.freeze({label: label});
});
