/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The search bar's grammar: what typed text becomes as a filter chip, how chips are added and removed, and how
 * chips travel in a link.
 *
 * A chip is {key, value}. "key: value" names a key; plain words, a key the page does not know and a key with no value
 * are searched as text. Two chips are the same when key and value match ignoring case, and a search holds at most
 * MAX_CHIPS of them (the server refuses more, each one costing a sub-select). Pure: the known keys come from the
 * caller, failures are returned as values.
 */
define([], function () {
    'use strict';

    var TEXT_KEY = 'text',
        MAX_CHIPS = 20,
        MAX_VALUE_LENGTH = 255,
        RANGE_PARAM = 'range',
        KEYED = /^([a-z_]+)\s*:\s*([\s\S]*)$/i,

        /**
         * Link parameters older pages used, and the key each one means now
         */
        LEGACY_PARAMS = Object.freeze({
            'correlation_id': 'request',
            'actor_name': 'by'
        });

    /**
     * @param {string} value
     * @returns {number} Length in characters (code points), as the server counts it
     */
    function length(value) {
        return Array.from(value).length;
    }

    /**
     * @param {Array<string>} knownKeys
     * @returns {function(string): boolean}
     */
    function knows(knownKeys) {
        var keys = {};

        (knownKeys || []).forEach(function (key) {
            keys[key] = true;
        });
        keys[TEXT_KEY] = true;

        return function (key) {
            return keys[key] === true;
        };
    }

    /**
     * @param {string} key
     * @param {string} value Already trimmed
     * @param {string} emptyError
     * @returns {{ok: boolean, chip: ?{key: string, value: string}, error: ?string}}
     */
    function chipOf(key, value, emptyError) {
        if (value === '') {
            return {ok: false, chip: null, error: emptyError};
        }
        if (length(value) > MAX_VALUE_LENGTH) {
            return {ok: false, chip: null, error: 'tooLong'};
        }

        return {ok: true, chip: {key: key, value: value}, error: null};
    }

    /**
     * Splits "key: value" typing at its first colon, whatever the key
     *
     * @param {string} raw
     * @returns {?{key: string, rest: string}} Null when the text names no key
     */
    function split(raw) {
        var match = KEYED.exec(String(raw || '').trim());

        return match === null ? null : {key: match[1].toLowerCase(), rest: match[2].trim()};
    }

    /**
     * The chip typed text stands for
     *
     * A known key with nothing after the colon ("by:") is searched as text, colon included, like an unknown key.
     * Errors: "empty" (nothing typed), "tooLong" (a value over MAX_VALUE_LENGTH characters).
     *
     * @param {string} raw What was typed
     * @param {Array<string>} knownKeys Keys the page searches by; "text" is always known
     * @returns {{ok: boolean, chip: ?{key: string, value: string}, error: ?string}}
     */
    function parse(raw, knownKeys) {
        var text = String(raw || '').trim(),
            keyed = split(text);

        if (keyed !== null && keyed.rest !== '' && knows(knownKeys)(keyed.key)) {
            return chipOf(keyed.key, keyed.rest, 'empty');
        }

        return chipOf(TEXT_KEY, text, 'empty');
    }

    /**
     * @param {{key: string, value: string}} one
     * @param {{key: string, value: string}} other
     * @returns {boolean}
     */
    function same(one, other) {
        return one.key === other.key && one.value.toLowerCase() === other.value.toLowerCase();
    }

    /**
     * @param {Array<{key: string, value: string}>} chips
     * @param {{key: string, value: string}} chip
     * @returns {boolean}
     */
    function has(chips, chip) {
        return chips.some(function (existing) {
            return same(existing, chip);
        });
    }

    /**
     * The chips with one more; a chip already there is not added twice
     *
     * Error: "limit" when the search already holds MAX_CHIPS chips.
     *
     * @param {Array<{key: string, value: string}>} chips
     * @param {{key: string, value: string}} chip
     * @returns {{ok: boolean, chips: Array<{key: string, value: string}>, added: boolean, error: ?string}}
     */
    function add(chips, chip) {
        if (has(chips, chip)) {
            return {ok: true, chips: chips.slice(), added: false, error: null};
        }
        if (chips.length >= MAX_CHIPS) {
            return {ok: false, chips: chips.slice(), added: false, error: 'limit'};
        }

        return {ok: true, chips: chips.concat([{key: chip.key, value: chip.value}]), added: true, error: null};
    }

    /**
     * @param {Array<{key: string, value: string}>} chips
     * @param {number} index
     * @returns {Array<{key: string, value: string}>} The chips without the one at index
     */
    function remove(chips, index) {
        return chips.filter(function (chip, position) {
            return position !== index;
        });
    }

    /**
     * @param {Array<{key: string, value: string}>} chips
     * @returns {Array<{key: string, value: string}>} The chips without the last one (Backspace on an empty input)
     */
    function removeLast(chips) {
        return chips.slice(0, -1);
    }

    /**
     * Chips and range a link carries: "?by=john&request=c7a1e9&range=30d"
     *
     * Every parameter named after a known key is a chip, in link order; the parameters older links used ("channel",
     * "correlation_id", "actor_name") still work; other parameters are not search terms and are left alone. Blank,
     * too long, repeated and excess values are dropped and counted.
     *
     * @param {string} search A query string, with or without the leading "?"
     * @param {Array<string>} knownKeys
     * @returns {{chips: Array<{key: string, value: string}>, rangeToken: ?string, dropped: number}}
     */
    function fromUrl(search, knownKeys) {
        var isKnown = knows(knownKeys),
            params = new URLSearchParams(String(search || '')),
            chips = [],
            dropped = 0,
            range = params.get(RANGE_PARAM);

        params.forEach(function (value, name) {
            var key = Object.prototype.hasOwnProperty.call(LEGACY_PARAMS, name) ? LEGACY_PARAMS[name] : name,
                parsed,
                added;

            if (name === RANGE_PARAM || !isKnown(key)) {
                return;
            }

            parsed = chipOf(key, value.trim(), 'empty');
            added = parsed.ok ? add(chips, parsed.chip) : null;
            if (added === null || !added.added) {
                dropped += 1;

                return;
            }
            chips = added.chips;
        });

        return {chips: chips, rangeToken: range === null || range === '' ? null : range, dropped: dropped};
    }

    /**
     * The query string of a link to this search
     *
     * @param {Array<{key: string, value: string}>} chips
     * @param {?string} rangeToken
     * @returns {string} Without the leading "?"
     */
    function toUrl(chips, rangeToken) {
        var params = new URLSearchParams();

        chips.forEach(function (chip) {
            params.append(chip.key, chip.value);
        });
        if (rangeToken) {
            params.append(RANGE_PARAM, rangeToken);
        }

        return params.toString();
    }

    return Object.freeze({
        TEXT_KEY: TEXT_KEY,
        MAX_CHIPS: MAX_CHIPS,
        MAX_VALUE_LENGTH: MAX_VALUE_LENGTH,
        split: split,
        parse: parse,
        same: same,
        add: add,
        remove: remove,
        removeLast: removeLast,
        fromUrl: fromUrl,
        toUrl: toUrl
    });
});
