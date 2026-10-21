/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Reads the grid filters a link asked for from a query string ("?correlation_id=a1f3c9e7"), keeping only the fields
 * the grid allows and dropping empty values. Pure: the caller passes the query string.
 */
define([], function () {
    'use strict';

    var MAX_LENGTH = 255;

    /**
     * @param {string} search A query string, with or without the leading "?"
     * @param {Array<string>} allowed Field names a link may filter by
     * @returns {Object<string, string>}
     */
    function read(search, allowed) {
        var params = new URLSearchParams(String(search || '')),
            values = {};

        allowed.forEach(function (field) {
            var value = params.get(field);

            if (typeof value === 'string' && value.trim() !== '' && value.length <= MAX_LENGTH) {
                values[field] = value.trim();
            }
        });

        return values;
    }

    return Object.freeze({read: read});
});
