/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the results of one search say around the activities themselves: which state the area is in, the count line,
 * the no-results explanation and the ways out of it, the narrow-the-search notice and the paging line
 *
 * - "emptyLog": nothing was ever recorded; "noResults": the log holds activities, none match; "results" otherwise.
 * - A count the server bounded reads "10,000+" (the wording's "atLeast"); a count with more to load reads
 *   "Showing 50 of 184,302".
 * - With no match in the range, the hint names how many matches lie outside it and when the latest was. Widening to
 *   WIDEN_TO is offered only when it would show something: the range is shorter than WIDEN_TO's and the latest match
 *   outside it falls inside WIDEN_TO's range. Removing the last filter and clearing them all are offered when there
 *   are filters.
 * Pure: wording, number and date writing are passed in.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/wording'
], function (wording) {
    'use strict';

    var WIDEN_TO = '30d';

    /**
     * A count as the area writes it
     *
     * @param {{count: number, atLeast: boolean}} bounded
     * @param {Object<string, string>} words
     * @param {function(number): string} formatNumber
     * @returns {string}
     */
    function countText(bounded, words, formatNumber) {
        return bounded.atLeast
            ? wording.fill(words.atLeast, formatNumber(bounded.count))
            : formatNumber(bounded.count);
    }

    /**
     * @param {{events: Array<Object>, journalEmpty: boolean}} page The first page of a search
     * @returns {string} "results", "emptyLog" or "noResults"
     */
    function stateOf(page) {
        if (page.events.length > 0) {
            return 'results';
        }

        return page.journalEmpty ? 'emptyLog' : 'noResults';
    }

    /**
     * The count line: "184 changes", "Showing 50 of 184,302 changes", "Showing 50 of 10,000+ events"
     *
     * @param {{changesOnly: boolean}} search
     * @param {?{count: number, atLeast: boolean}} total The first page's total
     * @param {number} loaded Activities shown so far
     * @param {Object<string, string>} words
     * @param {function(number): string} formatNumber
     * @returns {{count: string, noun: string}}
     */
    function head(search, total, loaded, words, formatNumber) {
        var known = total === null ? {count: loaded, atLeast: false} : total,
            partial = known.atLeast || known.count > loaded,
            single = !partial && known.count === 1;

        return {
            count: partial
                ? wording.fill(words.showingOf, formatNumber(loaded), countText(known, words, formatNumber))
                : formatNumber(known.count),
            noun: search.changesOnly ? (single ? words.changeOne : words.changeMany) : words.events
        };
    }

    /**
     * The explanation of a search with no match in its range, and the ways out of it
     *
     * @param {{chips: Array<Object>, range: {from: number, to: number}}} search
     * @param {{outside: ?{count: number, atLeast: boolean}, latestOutsideMs: ?number}} page
     * @param {Object<string, string>} words
     * @param {function(number): string} formatNumber
     * @param {function(number): string} formatTime
     * @param {?{from: number, to: number}} wider WIDEN_TO's range at this moment, or null when it has none
     * @returns {{hint: string, widen: boolean, removeLast: boolean, clearAll: boolean}}
     */
    function noResults(search, page, words, formatNumber, formatTime, wider) {
        var outside = page.outside !== null && page.outside.count > 0 ? page.outside : null,
            filtered = search.chips.length > 0,
            latest = page.latestOutsideMs,
            widens = outside !== null && wider !== null && latest !== null
                && search.range.to - search.range.from < wider.to - wider.from
                && latest >= wider.from && latest <= wider.to,
            hint;

        if (outside !== null && page.latestOutsideMs !== null) {
            hint = wording.fill(
                outside.count === 1 && !outside.atLeast ? words.outsideOne : words.outsideMany,
                countText(outside, words, formatNumber),
                formatTime(page.latestOutsideMs)
            );
        } else {
            hint = filtered ? words.noMatch : words.nothingInRange;
        }

        return {
            hint: hint,
            widen: widens,
            removeLast: filtered,
            clearAll: filtered
        };
    }

    /**
     * The notice that a text search looked through the newest activities only
     *
     * @param {{narrowed: boolean}} page
     * @param {number} candidateLimit
     * @param {Object<string, string>} words
     * @param {function(number): string} formatNumber
     * @returns {?string} Null when the whole range was searched
     */
    function narrow(page, candidateLimit, words, formatNumber) {
        return page.narrowed ? wording.fill(words.narrow, formatNumber(candidateLimit)) : null;
    }

    /**
     * The paging line under the activities
     *
     * @param {?string} nextCursor
     * @param {?{count: number, atLeast: boolean}} total The first page's total
     * @param {number} loaded
     * @param {number} pageSize
     * @param {Object<string, string>} words
     * @param {function(number): string} formatNumber
     * @returns {?{label: string, note: string}} Null when nothing more is to load
     */
    function paging(nextCursor, total, loaded, pageSize, words, formatNumber) {
        if (nextCursor === null) {
            return null;
        }

        return {
            label: wording.fill(words.loadMore, formatNumber(pageSize)),
            note: total === null
                ? ''
                : wording.fill(words.loadedOf, formatNumber(loaded), countText(total, words, formatNumber))
        };
    }

    /**
     * How a day's header counts its activities
     *
     * @param {number} count
     * @param {Object<string, string>} words
     * @param {function(number): string} formatNumber
     * @returns {string}
     */
    function dayCount(count, words, formatNumber) {
        return wording.count(count, words.dayEventsOne, words.dayEventsMany, formatNumber);
    }

    return Object.freeze({
        WIDEN_TO: WIDEN_TO,
        countText: countText,
        stateOf: stateOf,
        head: head,
        noResults: noResults,
        narrow: narrow,
        paging: paging,
        dayCount: dayCount
    });
});
