/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The event a part of the log page sends to ask the search for a filter: a grid cell offering "this actor", "this
 * channel" or "this request" announces it, and the page's search bar adds the chip — or, for a filter asked for
 * alone (the whole request), makes it the only chip. The grid never filters itself; it shows the search it is given.
 *
 * The event is NAME, bubbling, with the filter as its detail: {key, value, alone}. A key is a search key ("by",
 * "channel", "request"…); a value is trimmed text of 1 to MAX_VALUE characters; alone is true only when asked for.
 * Pure: it builds and reads the detail only.
 */
define([], function () {
    'use strict';

    var NAME = 'activity-search:criterion',
        MAX_VALUE = 255,
        KEY = /^[a-z][a-z_]{0,31}$/;

    /**
     * The detail of a request for this filter, or null when it is not one the search takes
     *
     * @param {*} key
     * @param {*} value
     * @param {*=} alone True to ask for this filter alone, replacing the others
     * @returns {?{key: string, value: string, alone: boolean}}
     */
    function detail(key, value, alone) {
        var text = typeof value === 'string' ? value.trim() : '';

        if (typeof key !== 'string' || !KEY.test(key) || text === '' || text.length > MAX_VALUE) {
            return null;
        }

        return Object.freeze({key: key, value: text, alone: alone === true});
    }

    /**
     * The filter an event's detail asks for, or null when the detail is not one
     *
     * @param {*} raw An event's detail
     * @returns {?{key: string, value: string, alone: boolean}}
     */
    function read(raw) {
        return raw !== null && typeof raw === 'object' ? detail(raw.key, raw.value, raw.alone) : null;
    }

    return Object.freeze({
        NAME: NAME,
        MAX_VALUE: MAX_VALUE,
        detail: detail,
        read: read
    });
});
