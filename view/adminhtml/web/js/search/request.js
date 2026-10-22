/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The search endpoints' contract as the page sees it: the query string of a search, and the result page and
 * suggestion lists the server answers with, checked and copied into the page's own shapes
 *
 * Query: "from" and "to" (ms, both included), one "c[]=key:value" per chip, "changes" ("1" or "0"), "cursor" (the
 * token the previous page returned) and "limit". A body of any other shape is refused with a SearchShapeError
 * rather than half-rendered, and a link that is neither http(s) nor a path from the site root is refused as well:
 * links go into href attributes. Pure.
 */
define([], function () {
    'use strict';

    var MAX_CHIPS = 20,
        MAX_LIMIT = 200,
        CHANGES_PARAM = 'changes',
        VERBS = ['changed', 'created', 'deleted', 'performed', 'tried_to_change', 'tried_to_delete'],
        KINDS = ['short', 'long', 'json', 'masked'],
        OUTCOMES = ['success', 'failure', 'denied'],
        GROUPS = ['fields', 'records', 'people', 'types', 'other'],
        KEY = /^[a-z][a-z_]{0,31}$/,
        LINK = /^(https?:\/\/|\/)/i;

    /**
     * @param {string} message
     * @constructor
     */
    function SearchShapeError(message) {
        this.name = 'SearchShapeError';
        this.message = message;
    }

    SearchShapeError.prototype = Object.create(Error.prototype);

    /**
     * @param {*} value
     * @returns {boolean}
     */
    function isObject(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }

    /**
     * @param {*} value
     * @returns {boolean}
     */
    function isCount(value) {
        return typeof value === 'number' && Math.floor(value) === value && value >= 0 && value <= Number.MAX_SAFE_INTEGER;
    }

    /**
     * @param {*} value
     * @param {string} what
     * @returns {*}
     */
    function present(value, what) {
        if (value === undefined) {
            throw new SearchShapeError(what + ' is missing');
        }

        return value;
    }

    /**
     * @param {*} value
     * @param {string} what
     * @returns {string}
     */
    function text(value, what) {
        if (typeof present(value, what) !== 'string') {
            throw new SearchShapeError(what + ' is not text');
        }

        return value;
    }

    /**
     * @param {*} value
     * @param {string} what
     * @returns {?string}
     */
    function optionalText(value, what) {
        return value === null ? null : text(value, what);
    }

    /**
     * @param {*} value
     * @param {string} what
     * @returns {number}
     */
    function count(value, what) {
        if (!isCount(present(value, what))) {
            throw new SearchShapeError(what + ' is not a whole number of 0 or more');
        }

        return value;
    }

    /**
     * @param {*} value
     * @param {string} what
     * @returns {boolean}
     */
    function flag(value, what) {
        if (typeof present(value, what) !== 'boolean') {
            throw new SearchShapeError(what + ' is not true or false');
        }

        return value;
    }

    /**
     * @param {*} value
     * @param {Array<string>} allowed
     * @param {string} what
     * @returns {string}
     */
    function oneOf(value, allowed, what) {
        if (allowed.indexOf(text(value, what)) === -1) {
            throw new SearchShapeError(what + ' "' + value + '" is not one the page knows');
        }

        return value;
    }

    /**
     * @param {*} value
     * @param {string} what
     * @returns {?string}
     */
    function optionalLink(value, what) {
        if (optionalText(value, what) !== null && !LINK.test(value)) {
            throw new SearchShapeError(what + ' is neither an http(s) address nor a path');
        }

        return value;
    }

    /**
     * @param {*} value
     * @param {string} what
     * @returns {Array<*>}
     */
    function list(value, what) {
        if (!Array.isArray(present(value, what))) {
            throw new SearchShapeError(what + ' is not a list');
        }

        return value;
    }

    /**
     * @param {*} value
     * @param {string} what
     * @returns {Object}
     */
    function record(value, what) {
        if (!isObject(present(value, what))) {
            throw new SearchShapeError(what + ' is not an object');
        }

        return value;
    }

    /**
     * @param {*} raw
     * @param {string} what
     * @returns {?{count: number, atLeast: boolean}}
     */
    function bounded(raw, what) {
        if (present(raw, what) === null) {
            return null;
        }
        record(raw, what);

        return {count: count(raw.count, what + ' count'), atLeast: flag(raw.atLeast, what + ' atLeast')};
    }

    /**
     * @param {*} raw
     * @returns {Object}
     */
    function field(raw) {
        record(raw, 'a field row');

        return {
            name: text(raw.name, 'a field name'),
            label: text(raw.label, 'a field label'),
            scope: optionalText(present(raw.scope, 'a field scope'), 'a field scope'),
            before: optionalText(present(raw.before, 'a value before'), 'a value before'),
            after: optionalText(present(raw.after, 'a value after'), 'a value after'),
            kind: oneOf(raw.kind, KINDS, 'a value kind'),
            changed: flag(raw.changed, 'a field\'s "changed"'),
            automatic: flag(raw.automatic, 'a field\'s "automatic"'),
            truncated: flag(raw.truncated, 'a field\'s "truncated"'),
            beforeLength: count(raw.beforeLength, 'a value\'s length before'),
            afterLength: count(raw.afterLength, 'a value\'s length after')
        };
    }

    /**
     * @param {*} raw
     * @returns {?Object}
     */
    function detail(raw) {
        if (present(raw, 'an activity\'s detail') === null) {
            return null;
        }
        record(raw, 'an activity\'s detail');

        return {
            what: optionalText(present(raw.what, 'a detail\'s "what"'), 'a detail\'s "what"'),
            fieldCount: count(raw.fieldCount, 'a detail\'s field count'),
            automaticCount: count(raw.automaticCount, 'a detail\'s automatic field count'),
            subjectTypeLabel: optionalText(present(raw.subjectTypeLabel, 'a subject type'), 'a subject type'),
            subjectLabel: optionalText(present(raw.subjectLabel, 'a detail\'s subject name'), 'a detail\'s subject name'),
            subjectUrl: optionalLink(present(raw.subjectUrl, 'a subject link'), 'a subject link'),
            fields: list(raw.fields, 'a detail\'s fields').map(field),
            moreFieldCount: count(raw.moreFieldCount, 'a detail\'s further field count'),
            unchangedCount: count(raw.unchangedCount, 'a detail\'s unchanged count'),
            unchangedRecorded: flag(raw.unchangedRecorded, 'a detail\'s "unchangedRecorded"')
        };
    }

    /**
     * @param {*} raw
     * @returns {?{type: string, id: ?string, label: ?string}}
     */
    function subject(raw) {
        if (present(raw, 'an activity\'s subject') === null) {
            return null;
        }
        record(raw, 'an activity\'s subject');

        return {
            type: text(raw.type, 'a subject type'),
            id: optionalText(present(raw.id, 'a subject id'), 'a subject id'),
            label: optionalText(present(raw.label, 'a subject label'), 'a subject label')
        };
    }

    /**
     * @param {*} raw
     * @returns {Object}
     */
    function event(raw) {
        var actor;

        record(raw, 'an activity');
        actor = record(raw.actor, 'an activity\'s actor');
        if (raw.durationMs !== null && (typeof raw.durationMs !== 'number' || !isFinite(raw.durationMs)
            || raw.durationMs < 0)) {
            throw new SearchShapeError('an activity\'s duration is not a number of 0 or more');
        }

        return {
            id: count(raw.id, 'an activity id'),
            occurredAtMs: count(raw.occurredAtMs, 'an activity\'s time'),
            channel: text(raw.channel, 'an activity\'s channel'),
            correlationId: text(raw.correlationId, 'an activity\'s request id'),
            siblingCount: count(raw.siblingCount, 'an activity\'s sibling count'),
            actor: {
                type: text(actor.type, 'an actor type'),
                name: text(actor.name, 'an actor name'),
                viaClient: optionalText(present(actor.viaClient, 'a client'), 'a client'),
                viaVersion: optionalText(present(actor.viaVersion, 'a client version'), 'a client version'),
                viaChannel: optionalText(present(actor.viaChannel, 'a client\'s channel'), 'a client\'s channel')
            },
            action: text(raw.action, 'an activity\'s action'),
            outcome: oneOf(raw.outcome, OUTCOMES, 'an outcome'),
            message: optionalText(present(raw.message, 'an activity\'s message'), 'an activity\'s message'),
            durationMs: present(raw.durationMs, 'an activity\'s duration'),
            ip: optionalText(present(raw.ip, 'an activity\'s IP'), 'an activity\'s IP'),
            subject: subject(raw.subject),
            verb: oneOf(raw.verb, VERBS, 'a verb'),
            detail: detail(raw.detail),
            detailUrl: optionalLink(text(raw.detailUrl, 'a detail link'), 'a detail link')
        };
    }

    /**
     * A result page
     *
     * @param {*} raw The decoded JSON body of a successful search
     * @returns {{events: Array<Object>, nextCursor: ?string, narrowed: boolean,
     *     total: ?{count: number, atLeast: boolean}, outside: ?{count: number, atLeast: boolean},
     *     latestOutsideMs: ?number, overall: ?{count: number, atLeast: boolean}, journalEmpty: boolean}}
     * @throws {SearchShapeError}
     */
    function parsePage(raw) {
        var cursor;

        record(raw, 'the result page');
        cursor = optionalText(present(raw.nextCursor, 'the next page position'), 'the next page position');
        if (cursor === '') {
            throw new SearchShapeError('the next page position is empty');
        }

        return {
            events: list(raw.events, 'the results').map(event),
            nextCursor: cursor,
            narrowed: flag(raw.narrowed, '"narrowed"'),
            total: bounded(raw.total, 'the total'),
            outside: bounded(raw.outside, 'the count outside the range'),
            latestOutsideMs: present(raw.latestOutsideMs, 'the latest time outside the range') === null
                ? null
                : count(raw.latestOutsideMs, 'the latest time outside the range'),
            overall: bounded(raw.overall, 'the overall count'),
            journalEmpty: flag(raw.journalEmpty, '"journalEmpty"')
        };
    }

    /**
     * @param {*} raw
     * @returns {{key: string, label: string, hint: string, group: string, matchesFieldNames: boolean}}
     */
    function keyDescription(raw) {
        record(raw, 'a search key');
        if (!KEY.test(text(raw.key, 'a search key'))) {
            throw new SearchShapeError('a search key is malformed');
        }

        return {
            key: raw.key,
            label: text(raw.label, 'a key label'),
            hint: text(raw.hint, 'a key hint'),
            group: oneOf(raw.group, GROUPS, 'a suggestion group'),
            matchesFieldNames: flag(raw.matchesFieldNames, 'a key\'s "matchesFieldNames"')
        };
    }

    /**
     * @param {*} raw
     * @returns {{value: string, label: string, hint: string, count: ?number}}
     */
    function suggestion(raw) {
        record(raw, 'a suggestion');

        return {
            value: text(raw.value, 'a suggested value'),
            label: text(raw.label, 'a suggestion label'),
            hint: text(raw.hint, 'a suggestion hint'),
            count: present(raw.count, 'a suggestion count') === null ? null : count(raw.count, 'a suggestion count')
        };
    }

    /**
     * The keys and suggestion lists the search bar works from
     *
     * @param {*} raw The decoded JSON body of the suggestion endpoint
     * @returns {{keys: Array<{key: string, label: string, hint: string, group: string, matchesFieldNames: boolean}>,
     *     lists: Object<string, Array<{value: string, label: string, hint: string, count: ?number}>>}}
     * @throws {SearchShapeError}
     */
    function parseSuggestions(raw) {
        var lists = {},
            rawLists;

        record(raw, 'the suggestions');
        rawLists = present(raw.lists, 'the suggestion lists');
        if (Array.isArray(rawLists) && rawLists.length === 0) {
            rawLists = {};
        }
        record(rawLists, 'the suggestion lists');
        Object.keys(rawLists).forEach(function (key) {
            if (!KEY.test(key)) {
                throw new SearchShapeError('a suggestion list has a malformed key');
            }
            lists[key] = list(rawLists[key], 'a suggestion list').map(suggestion);
        });

        return {keys: list(raw.keys, 'the search keys').map(keyDescription), lists: lists};
    }

    /**
     * The reason the server gave for refusing a search, if the body carries one
     *
     * @param {*} raw The decoded JSON body of a refused search
     * @returns {?string}
     */
    function refusal(raw) {
        return isObject(raw) && typeof raw.error === 'string' && raw.error !== '' ? raw.error : null;
    }

    /**
     * @param {*} value
     * @param {string} what
     * @returns {number}
     */
    function milliseconds(value, what) {
        if (!isCount(value)) {
            throw new RangeError(what + ' is not a time in ms of 0 or more');
        }

        return value;
    }

    /**
     * The query string of a search
     *
     * @param {{range: {from: number, to: number}, chips: Array<{key: string, value: string}>,
     *     changesOnly: boolean, cursor: ?string, limit: ?number}} search
     * @returns {string} Without the leading "?"
     * @throws {RangeError} On a range that ends before it starts, more than MAX_CHIPS chips or a limit out of bounds
     */
    function toQuery(search) {
        var params = new URLSearchParams(),
            from = milliseconds(search.range.from, 'The start'),
            to = milliseconds(search.range.to, 'The end');

        if (from > to) {
            throw new RangeError('The search range ends before it starts');
        }
        if (search.chips.length > MAX_CHIPS) {
            throw new RangeError('A search takes at most ' + MAX_CHIPS + ' filters');
        }
        if (search.limit !== undefined && search.limit !== null
            && (!isCount(search.limit) || search.limit < 1 || search.limit > MAX_LIMIT)) {
            throw new RangeError('A page holds 1 to ' + MAX_LIMIT + ' activities');
        }

        params.append('from', String(from));
        params.append('to', String(to));
        search.chips.forEach(function (chip) {
            params.append('c[]', chip.key + ':' + chip.value);
        });
        params.append(CHANGES_PARAM, search.changesOnly ? '1' : '0');
        if (search.cursor) {
            params.append('cursor', search.cursor);
        }
        if (search.limit) {
            params.append('limit', String(search.limit));
        }

        return params.toString();
    }

    /**
     * The search for the other activities of one request: every channel, the whole log up to now, since a request
     * may straddle the edge of the range being viewed
     *
     * @param {string} correlationId
     * @param {number} nowMs
     * @returns {{range: {from: number, to: number}, chips: Array<{key: string, value: string}>,
     *     changesOnly: boolean, cursor: null, limit: number}}
     */
    function siblingsOf(correlationId, nowMs) {
        return {
            range: {from: 0, to: nowMs},
            chips: [{key: 'request', value: correlationId}],
            changesOnly: false,
            cursor: null,
            limit: MAX_LIMIT
        };
    }

    /**
     * An endpoint address with a query string added; the address keeps its own parameters (the admin's secret key)
     *
     * @param {string} url
     * @param {string} query
     * @returns {string}
     */
    function withQuery(url, query) {
        if (query === '') {
            return url;
        }

        return url + (url.indexOf('?') === -1 ? '?' : '&') + query;
    }

    return Object.freeze({
        VERBS: Object.freeze(VERBS.slice()),
        KINDS: Object.freeze(KINDS.slice()),
        OUTCOMES: Object.freeze(OUTCOMES.slice()),
        GROUPS: Object.freeze(GROUPS.slice()),
        toQuery: toQuery,
        siblingsOf: siblingsOf,
        withQuery: withQuery,
        parsePage: parsePage,
        parseSuggestions: parseSuggestions,
        refusal: refusal,
        SearchShapeError: SearchShapeError
    });
});
