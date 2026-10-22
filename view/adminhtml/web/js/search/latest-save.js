/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Saves sent one at a time, so the last one asked for is the one that stays
 *
 * Two quick saves sent together may reach the server in either order, and the older one may land last. Here a save
 * asked for while another is on its way waits for it; of the saves that waited, only the newest is sent, since it
 * replaces the others anyway. Pure: the sending is passed in.
 */
define([], function () {
    'use strict';

    /**
     * @param {function(*): Promise<*>} send Sends one save; its promise settles when the server answered or gave up
     * @returns {{save: function(*)}}
     */
    function create(send) {
        var busy = false,
            waiting = null;

        /**
         * @param {*} value
         */
        function run(value) {
            busy = true;
            Promise.resolve().then(function () {
                return send(value);
            }).then(next, next);
        }

        /** Sends the newest save that waited, if any. */
        function next() {
            var value;

            busy = false;
            if (waiting !== null) {
                value = waiting.value;
                waiting = null;
                run(value);
            }
        }

        return Object.freeze({
            /**
             * @param {*} value What to save; it replaces any save still waiting
             */
            save: function (value) {
                if (busy) {
                    waiting = {value: value};

                    return;
                }
                run(value);
            }
        });
    }

    return Object.freeze({create: create});
});
