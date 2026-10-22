/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The search page's search: the chips, the time range, "Changes only" and the result view, and the forms it travels
 * in — the link's query string and the grid's search parameter.
 *
 * A page opened from a link takes its chips and range from the link (see grammar.fromUrl and range.fromToken), the
 * rest from the admin user's choices. "Changes only" starts off whatever the choice when the link asks for something
 * only other activities have: a request's activities ("request") or a channel that records no changes. The admin's
 * menu links "?channel=admin" and "?channel=entity" therefore open on what they name.
 *
 * Every request is made with the range brought up to date first (range.refresh), so a page left open still asks for
 * "the last hour" of now. Pure: the clock's reading and the store's timezone are passed in.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/grammar',
    'Hryvinskyi_ActivityLog/js/search/range'
], function (grammar, range) {
    'use strict';

    var REQUEST_KEY = 'request',
        CHANNEL_KEY = 'channel',
        VIEWS = Object.freeze(['timeline', 'grid']);

    /**
     * @param {*} text
     * @returns {string}
     */
    function lower(text) {
        return String(text === null || text === undefined ? '' : text).trim().toLowerCase();
    }

    /**
     * Whether a channel chip names a channel that records changes, by code or by label
     *
     * @param {string} value
     * @param {Array<string>} changeChannels Codes
     * @param {Object<string, {label: string}>} channels By code
     * @returns {boolean}
     */
    function namesChangeChannel(value, changeChannels, channels) {
        var wanted = lower(value);

        return changeChannels.some(function (code) {
            var channel = channels[code];

            return lower(code) === wanted || (channel !== undefined && lower(channel.label) === wanted);
        });
    }

    /**
     * Whether the chips ask for activities "Changes only" would hide: those of one request, or of a channel that
     * records no changes
     *
     * @param {Array<{key: string, value: string}>} chips
     * @param {Array<string>} changeChannels
     * @param {Object<string, {label: string}>} channels
     * @returns {boolean}
     */
    function needsAllActivity(chips, changeChannels, channels) {
        return chips.some(function (chip) {
            return chip.key === REQUEST_KEY
                || (chip.key === CHANNEL_KEY && !namesChangeChannel(chip.value, changeChannels, channels));
        });
    }

    /**
     * The search a page opens with
     *
     * @param {{search: string, keys: Array<string>, nowMs: number, zone: Object,
     *     preferences: {view: string, changesOnly: boolean}, changeChannels: Array<string>,
     *     channels: Object<string, {label: string}>}} input search is the link's query string
     * @returns {{chips: Array<{key: string, value: string}>, range: {preset: string, from: number, to: number},
     *     changesOnly: boolean, view: string, dropped: number}} dropped counts link parameters that were not used
     */
    function initial(input) {
        var link = grammar.fromUrl(input.search, input.keys),
            fromLink = link.rangeToken === null ? null : range.fromToken(link.rangeToken, input.nowMs, input.zone),
            opened = fromLink !== null && fromLink.ok
                ? fromLink.range
                : range.preset(range.DEFAULT_PRESET, input.nowMs, input.zone).range,
            offered = input.changeChannels.length > 0;

        return {
            chips: link.chips,
            range: opened,
            changesOnly: offered && input.preferences.changesOnly
                && !needsAllActivity(link.chips, input.changeChannels, input.channels),
            view: VIEWS.indexOf(input.preferences.view) === -1 ? VIEWS[0] : input.preferences.view,
            dropped: link.dropped + (fromLink !== null && !fromLink.ok ? 1 : 0)
        };
    }

    /**
     * @param {Object} state
     * @param {Object} changes
     * @returns {Object} A copy of the state with the changes
     */
    function update(state, changes) {
        return Object.assign({}, state, changes);
    }

    /**
     * The search after a part of the page asked for a filter: one more (a grid cell's actor, channel or request), or
     * that filter alone (a timeline item's actor or request: everything that actor did, the whole request)
     *
     * The filter obeys the grammar as typed text does; the range stays. One that only other activities have
     * switches "Changes only" off, as following a request does; the admin's own choice of the switch is left as it
     * was.
     *
     * @param {Object} state
     * @param {{key: string, value: string, alone: (boolean|undefined)}} criterion alone replaces the chips
     * @param {{keys: Array<string>, changeChannels: Array<string>, channels: Object}} context
     * @returns {{ok: boolean, state: Object, error: ?string}} errors as grammar.parse and grammar.add give them
     */
    function withCriterion(state, criterion, context) {
        var parsed = grammar.parse(criterion.key + ': ' + criterion.value, context.keys),
            added = parsed.ok ? grammar.add(criterion.alone === true ? [] : state.chips, parsed.chip) : null;

        if (added === null || !added.ok) {
            return {ok: false, state: state, error: added === null ? parsed.error : added.error};
        }

        return {
            ok: true,
            state: update(state, {
                chips: added.chips,
                changesOnly: state.changesOnly
                    && !needsAllActivity([parsed.chip], context.changeChannels, context.channels)
            }),
            error: null
        };
    }

    /**
     * The search an example stands for: its filters, over its range at this moment
     *
     * @param {Object} state
     * @param {{chips: Array<{key: string, value: string}>, preset: string}} example
     * @param {Array<string>} keys Known keys
     * @param {number} nowMs
     * @param {Object} zone
     * @returns {{ok: boolean, state: Object, error: ?string}} The state unchanged when a filter or the range is not
     *     one the page takes
     */
    function withExample(state, example, keys, nowMs, zone) {
        var chips = [],
            error = null,
            opened = range.preset(example.preset, nowMs, zone);

        example.chips.forEach(function (chip) {
            var parsed = error === null ? grammar.parse(chip.key + ': ' + chip.value, keys) : null,
                added = parsed !== null && parsed.ok ? grammar.add(chips, parsed.chip) : null;

            if (parsed === null) {
                return;
            }
            if (added === null || !added.ok) {
                error = added === null ? parsed.error : added.error;

                return;
            }
            chips = added.chips;
        });
        if (error === null && !opened.ok) {
            error = opened.error;
        }

        return error === null
            ? {ok: true, state: update(state, {chips: chips, range: opened.range}), error: null}
            : {ok: false, state: state, error: error};
    }

    /**
     * The search as one request asks for it, its range brought up to date
     *
     * @param {{chips: Array<Object>, range: Object, changesOnly: boolean}} state
     * @param {number} nowMs
     * @param {Object} zone
     * @returns {{chips: Array<{key: string, value: string}>, range: {preset: string, from: number, to: number},
     *     changesOnly: boolean}} A frozen copy
     */
    function snapshot(state, nowMs, zone) {
        var current = range.refresh(state.range, nowMs, zone);

        return Object.freeze({
            chips: Object.freeze(state.chips.map(function (chip) {
                return Object.freeze({key: chip.key, value: chip.value});
            })),
            range: Object.freeze({preset: current.preset, from: current.from, to: current.to}),
            changesOnly: state.changesOnly
        });
    }

    /**
     * The grid's search parameter for a search: the search endpoint's own parameters
     *
     * @param {{chips: Array<{key: string, value: string}>, range: {from: number, to: number},
     *     changesOnly: boolean}} search
     * @returns {{from: string, to: string, c: Array<string>, changes: string}}
     */
    function gridParam(search) {
        return {
            from: String(search.range.from),
            to: String(search.range.to),
            c: search.chips.map(function (chip) {
                return chip.key + ':' + chip.value;
            }),
            changes: search.changesOnly ? '1' : '0'
        };
    }

    /**
     * The query string of a link to the search; the default range is left out
     *
     * @param {{chips: Array<{key: string, value: string}>, range: {preset: string, from: number, to: number}}} state
     * @returns {string} Without the leading "?"
     */
    function linkQuery(state) {
        return grammar.toUrl(
            state.chips,
            state.range.preset === range.DEFAULT_PRESET ? null : range.toToken(state.range)
        );
    }

    return Object.freeze({
        VIEWS: VIEWS,
        needsAllActivity: needsAllActivity,
        initial: initial,
        update: update,
        withCriterion: withCriterion,
        withExample: withExample,
        snapshot: snapshot,
        gridParam: gridParam,
        linkQuery: linkQuery
    });
});
