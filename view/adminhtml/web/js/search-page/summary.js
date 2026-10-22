/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The line beside the search options that sums up a filtered search: "2 filters · 12 matching changes overall, 8 in
 * range". It is empty while the search has no filter.
 *
 * The counts come from the first page of the search. The timeline reads that page anyway and reports it (report);
 * while the grid is shown the line asks the search for a one-activity page of its own (follow with "grid"). Only the
 * latest search's counts are written. A request leaves nothing behind once it settled: its hold on the page's signal
 * is released.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/wording'
], function (wording) {
    'use strict';

    /**
     * @param {HTMLElement} element The summary element
     * @param {{words: Object<string, string>, formatNumber: function(number): string,
     *     countText: function({count: number, atLeast: boolean}): string,
     *     search: {page: function(Object, AbortSignal): Promise<Object>}, report: function(*),
     *     signal: AbortSignal}} options words: the page's wording (filtersOne/Many, overallOne/Many)
     * @returns {{follow: function(Object, string), report: function(Object, Object)}}
     */
    function create(element, options) {
        var words = options.words,
            latest = null,
            request = null;

        /**
         * @param {Object} search
         * @param {?Object} page The search's first page, null while its counts are not known
         */
        function write(search, page) {
            var filters = wording.count(search.chips.length, words.filtersOne, words.filtersMany,
                    options.formatNumber),
                overall = page === null ? null : page.overall,
                total = page === null ? null : page.total;

            if (search.chips.length === 0) {
                element.textContent = '';

                return;
            }
            element.textContent = overall === null || total === null
                ? filters
                : filters + ' · ' + wording.fill(
                    overall.count === 1 && !overall.atLeast ? words.overallOne : words.overallMany,
                    options.countText(overall),
                    options.countText(total)
                );
        }

        /**
         * The search shown now
         *
         * @param {{chips: Array<Object>, range: {from: number, to: number}, changesOnly: boolean}} search
         * @param {string} view "timeline" (its pages are reported) or "grid" (the line asks for the counts)
         */
        function follow(search, view) {
            var mine,
                stop,
                settled;

            if (request !== null) {
                request.abort();
                request = null;
            }
            latest = search;
            write(search, null);
            if (view !== 'grid' || search.chips.length === 0 || options.signal.aborted) {
                return;
            }
            mine = new AbortController();
            request = mine;
            stop = function () {
                mine.abort();
            };
            settled = function () {
                options.signal.removeEventListener('abort', stop);
                if (request === mine) {
                    request = null;
                }
            };
            options.signal.addEventListener('abort', stop, {once: true});
            options.search.page({
                range: {from: search.range.from, to: search.range.to},
                chips: search.chips,
                changesOnly: search.changesOnly,
                cursor: null,
                limit: 1
            }, mine.signal).then(function (page) {
                settled();
                if (latest === search) {
                    write(search, page);
                }
            }, function (failure) {
                settled();
                if (latest === search && !(failure && failure.kind === 'aborted')) {
                    options.report(failure);
                }
            });
        }

        /**
         * The first page of a search, as the timeline read it
         *
         * @param {Object} search
         * @param {Object} page
         */
        function report(search, page) {
            if (latest === search) {
                write(search, page);
            }
        }

        return Object.freeze({follow: follow, report: report});
    }

    return Object.freeze({create: create});
});
