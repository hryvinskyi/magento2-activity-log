/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * A request's signal: it aborts when its owner's signal aborts (the page closes, a newer search starts) or when the
 * deadline passes, whichever comes first. "done" releases the timer and the listener once the request settled.
 *
 * The timers and the AbortController come from the browser object passed in (window in the page, a fake in tests).
 */
define([], function () {
    'use strict';

    /**
     * @param {{setTimeout: Function, clearTimeout: Function, AbortController: Function}} browser
     * @param {AbortSignal} owner
     * @param {number} timeoutMs
     * @returns {{signal: AbortSignal, timedOut: function(): boolean, done: function()}}
     */
    function start(browser, owner, timeoutMs) {
        var controller = new browser.AbortController(),
            expired = false,
            stop = function () {
                controller.abort();
            },
            timer = browser.setTimeout(function () {
                expired = true;
                stop();
            }, timeoutMs);

        if (owner.aborted) {
            stop();
        } else {
            owner.addEventListener('abort', stop, {once: true});
        }

        return {
            signal: controller.signal,
            timedOut: function () {
                return expired;
            },
            done: function () {
                browser.clearTimeout(timer);
                owner.removeEventListener('abort', stop);
            }
        };
    }

    return Object.freeze({start: start});
});
