/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Filling the server's translated wording: "%1", "%2"… stand for the values put in, in order, as Magento's own
 * phrases write them. A value is put in as text; a placeholder with no value is left as it is. Pure.
 */
define([], function () {
    'use strict';

    var PLACEHOLDER = /%(\d+)/g;

    /**
     * @param {string} template A translated string
     * @param {...*} values
     * @returns {string}
     */
    function fill(template) {
        var values = Array.prototype.slice.call(arguments, 1);

        return String(template).replace(PLACEHOLDER, function (placeholder, position) {
            var index = Number(position) - 1;

            return index >= 0 && index < values.length ? String(values[index]) : placeholder;
        });
    }

    /**
     * The singular or the plural of a text, by a count
     *
     * @param {number} count
     * @param {string} one The text for exactly one, with "%1" for the count
     * @param {string} many The text for any other count
     * @param {function(number): string} formatNumber How the count is written
     * @returns {string}
     */
    function count(count, one, many, formatNumber) {
        return fill(count === 1 ? one : many, formatNumber(count));
    }

    return Object.freeze({
        fill: fill,
        count: count
    });
});
