/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Which of the grid's selection actions the grid offers while its count is only a lower bound ("10,000+"): every one
 * but "Select All", since nobody could see how many activities it would take. Pure.
 */
define([], function () {
    'use strict';

    var SELECT_ALL = 'selectAll';

    /**
     * @param {string} actionId A selection action of the grid ("selectAll", "selectPage", …)
     * @param {*} totalAtLeast Whether more activities match than the grid counted
     * @returns {boolean} False when the action is withheld; true leaves the choice to the grid's own rules
     */
    function allows(actionId, totalAtLeast) {
        return !(totalAtLeast === true && actionId === SELECT_ALL);
    }

    return Object.freeze({allows: allows});
});
