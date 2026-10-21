/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Which channels the dashboard shows after a chip is pressed. The last active chip cannot be switched off, and the
 * selection keeps the chips' order. Pure.
 */
define([], function () {
    'use strict';

    /**
     * @param {Array<string>} order Every chip, in display order
     * @param {Array<string>} active Channels shown now
     * @param {string} code The chip pressed
     * @returns {Array<string>} The new selection
     */
    function toggle(order, active, code) {
        var isActive = active.indexOf(code) !== -1;

        if (isActive && active.length === 1) {
            return active.slice();
        }

        return order.filter(function (channel) {
            return channel === code ? !isActive : active.indexOf(channel) !== -1;
        });
    }

    return Object.freeze({toggle: toggle});
});
