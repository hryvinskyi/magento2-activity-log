/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The activity search endpoint as the timeline reads it: one page of results for a search
 *
 * Public surface, for any page that shows the timeline: create(url, timeoutMs, browser) returns the search port the
 * timeline mounts with, {page(query, signal)}. The url is the endpoint address the server built (it carries the
 * admin's secret key); the query string is added to it. Every request ends at the deadline or when the caller's
 * signal aborts. The answer is parsed (search/request.js) before anyone sees it.
 *
 * A failed page rejects with a SearchFailure: kind "aborted" (the caller gave up — nothing to show), "refused" (the
 * server refused the search and said why, in the admin's language: reason) or "failed" (anything else; the cause is
 * kept for the log, never shown).
 */
define([
    'Hryvinskyi_ActivityLog/js/search/request',
    'Hryvinskyi_ActivityLog/js/search/deadline'
], function (request, deadline) {
    'use strict';

    var REFUSED_STATUS = 400;

    /**
     * @param {string} kind "aborted", "refused" or "failed"
     * @param {?string} reason The server's reason for a refusal
     * @param {*=} cause
     * @constructor
     */
    function SearchFailure(kind, reason, cause) {
        this.name = 'SearchFailure';
        this.kind = kind;
        this.reason = reason;
        this.cause = cause;
        this.message = 'The activity search ' + kind + (cause && cause.message ? ': ' + cause.message : '');
    }

    SearchFailure.prototype = Object.create(Error.prototype);

    /**
     * @param {{status: number, json: function(): Promise<*>}} response
     * @returns {Promise<*>} The decoded body, or null when it is not JSON
     */
    function bodyOf(response) {
        return response.json().then(null, function () {
            return null;
        });
    }

    /**
     * @param {{ok: boolean, status: number, json: function(): Promise<*>}} response
     * @returns {Promise<Object>} The parsed page
     */
    function answer(response) {
        return bodyOf(response).then(function (body) {
            var reason = response.status === REFUSED_STATUS ? request.refusal(body) : null;

            if (reason !== null) {
                throw new SearchFailure('refused', reason);
            }
            if (!response.ok) {
                throw new SearchFailure('failed', null, new Error('The search answered with status ' + response.status));
            }
            try {
                return request.parsePage(body);
            } catch (malformed) {
                throw new SearchFailure('failed', null, malformed);
            }
        });
    }

    /**
     * @param {string} url The search endpoint address
     * @param {number} timeoutMs How long a page may take
     * @param {{fetch: Function, setTimeout: Function, clearTimeout: Function, AbortController: Function}} browser
     * @returns {{page: function(Object, AbortSignal): Promise<Object>}}
     */
    function create(url, timeoutMs, browser) {
        /**
         * One page of results
         *
         * @param {{range: {from: number, to: number}, chips: Array<{key: string, value: string}>,
         *     changesOnly: boolean, cursor: ?string, limit: ?number}} query
         * @param {AbortSignal} signal Aborted when the caller no longer wants the page
         * @returns {Promise<Object>} As request.parsePage returns it; rejects with a SearchFailure
         */
        function page(query, signal) {
            var limit = deadline.start(browser, signal, timeoutMs),
                address;

            try {
                address = request.withQuery(url, request.toQuery(query));
            } catch (invalid) {
                limit.done();

                return Promise.reject(new SearchFailure('failed', null, invalid));
            }

            return browser.fetch(address, {
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                signal: limit.signal
            }).then(answer).then(function (parsed) {
                limit.done();

                return parsed;
            }, function (error) {
                limit.done();
                if (error instanceof SearchFailure) {
                    throw error;
                }
                if (signal.aborted) {
                    throw new SearchFailure('aborted', null, error);
                }
                throw new SearchFailure('failed', null, limit.timedOut() ? new Error('The search timed out') : error);
            });
        }

        return Object.freeze({page: page});
    }

    return Object.freeze({
        create: create,
        SearchFailure: SearchFailure
    });
});
