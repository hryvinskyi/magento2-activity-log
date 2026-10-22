/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The event the search page sends its results area each time the search changes, and once when the page starts:
 * NAME, not bubbling, with the search as its detail — {chips, range: {preset, from, to}, changesOnly, view}, the
 * range brought up to date for this request. Whatever shows results listens on the results element and shows the
 * search it is told, never one of its own. Pure: it builds and reads the detail only.
 */
define([], function () {
    'use strict';

    var NAME = 'activity-search:search',
        VIEWS = ['timeline', 'grid'];

    /**
     * The detail announcing a search
     *
     * @param {{chips: Array<{key: string, value: string}>, range: {preset: string, from: number, to: number},
     *     changesOnly: boolean}} search As search-state.snapshot returns it
     * @param {string} view "timeline" or "grid"
     * @returns {Object} Frozen
     */
    function detail(search, view) {
        return Object.freeze({
            chips: search.chips,
            range: search.range,
            changesOnly: search.changesOnly,
            view: view
        });
    }

    /**
     * The search a detail announces, or null when it is not one
     *
     * @param {*} raw An event's detail
     * @returns {?Object}
     */
    function read(raw) {
        if (raw === null || typeof raw !== 'object' || !Array.isArray(raw.chips) || raw.range === null
            || typeof raw.range !== 'object' || typeof raw.range.from !== 'number' || typeof raw.range.to !== 'number'
            || typeof raw.changesOnly !== 'boolean' || VIEWS.indexOf(raw.view) === -1) {
            return null;
        }

        return raw;
    }

    return Object.freeze({
        NAME: NAME,
        detail: detail,
        read: read
    });
});
