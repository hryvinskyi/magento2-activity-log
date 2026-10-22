/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * "Now" as the server counts it, read on every call
 *
 * The page prints the server's time once; the difference to the browser's clock at that moment is the skew, and every
 * later reading is the browser's clock plus that skew. A page left open keeps the right time, and a browser whose
 * clock is off still asks the server for the right range. Pure: the browser's clock is passed in.
 */
define([], function () {
    'use strict';

    /**
     * @param {*} value
     * @param {string} what
     * @returns {number}
     */
    function milliseconds(value, what) {
        if (typeof value !== 'number' || !isFinite(value) || Math.floor(value) !== value) {
            throw new TypeError(what + ' is not a time in milliseconds');
        }

        return value;
    }

    /**
     * @param {number} serverNowMs The server's time the page printed
     * @param {number} browserNowMs The browser's time when the page read it
     * @param {function(): number} readBrowserNow The browser's clock, such as Date.now
     * @returns {{skewMs: number, now: function(): number}}
     */
    function create(serverNowMs, browserNowMs, readBrowserNow) {
        var skewMs = milliseconds(serverNowMs, 'The server time') - milliseconds(browserNowMs, 'The browser time');

        if (typeof readBrowserNow !== 'function') {
            throw new TypeError('The browser clock is not a function');
        }

        return Object.freeze({
            skewMs: skewMs,
            now: function () {
                return Math.round(readBrowserNow()) + skewMs;
            }
        });
    }

    return Object.freeze({create: create});
});
