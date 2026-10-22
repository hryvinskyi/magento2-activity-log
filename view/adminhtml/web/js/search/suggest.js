/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the search bar offers while the admin types, from the lists the server sent once
 *
 * Groups, in order:
 * - "keys" ("Filter by"): keys to filter by; every key while nothing is typed, else those whose code or label starts
 *   with the typed text;
 * - "group": for plain words, one group per suggestion group of the keys (fields, records, people, types, in that
 *   order), holding the values of all its keys whose code or label contains the words; keys of the "other" group
 *   are offered only once their key is typed;
 * - "matching": after "key:", that key's values matching what follows the colon;
 * - "text": the typed words searched as text ("anywhere"), or a "key:" the page does not know ("unknownKey");
 * - "typed" ("Use as typed"): after "key: value", the value as typed.
 * Values already used as chips are left out. Groups carry codes, not titles: the page prints its own wording. Each
 * key's suggestion group comes from the server with the key. Pure: no filtering on the server, no scan of the log.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/grammar'
], function (grammar) {
    'use strict';

    var LIMITS = Object.freeze({
            keys: 6,
            allKeys: 32,
            matching: 8,
            fields: 6,
            records: 6,
            people: 6,
            types: 4
        }),

        /**
         * Suggestion groups offered for plain words, in display order
         */
        WORD_GROUPS = Object.freeze(['fields', 'records', 'people', 'types']);

    /**
     * @param {*} text
     * @returns {string}
     */
    function lower(text) {
        return String(text === null || text === undefined ? '' : text).toLowerCase();
    }

    /**
     * @param {Array<{key: string, value: string}>} chips
     * @returns {function({key: string, value: string}): boolean}
     */
    function unused(chips) {
        return function (item) {
            return !chips.some(function (chip) {
                return grammar.same(chip, item);
            });
        };
    }

    /**
     * @param {{key: string, label: string, hint: string}} description
     * @returns {Object}
     */
    function keyItem(description) {
        return {kind: 'key', key: description.key, value: '', label: description.label, hint: description.hint, count: null};
    }

    /**
     * @param {string} key
     * @param {{value: string, label: string, hint: string, count: ?number}} entry
     * @returns {Object}
     */
    function valueItem(key, entry) {
        return {kind: 'value', key: key, value: entry.value, label: entry.label, hint: entry.hint, count: entry.count};
    }

    /**
     * @param {string} query Lowercase
     * @returns {function(Object): boolean}
     */
    function contains(query) {
        return function (item) {
            return query === '' || lower(item.value).indexOf(query) !== -1 || lower(item.label).indexOf(query) !== -1;
        };
    }

    /**
     * @param {Array<Object>} groups
     * @param {Object} group
     * @param {Array<Object>} items
     * @param {number} limit
     */
    function push(groups, group, items, limit) {
        if (items.length > 0) {
            group.items = items.slice(0, limit);
            groups.push(group);
        }
    }

    /**
     * @param {{keys: Array<Object>, lists: Object<string, Array<Object>>}} vocabulary
     * @param {string} key
     * @param {function(Object): boolean} keep
     * @returns {Array<Object>}
     */
    function valuesOf(vocabulary, key, keep) {
        return (vocabulary.lists[key] || []).map(function (entry) {
            return valueItem(key, entry);
        }).filter(keep);
    }

    /**
     * @param {string} raw
     * @param {string} hint "anywhere" or "unknownKey"
     * @returns {{type: string, group: null, key: string, items: Array<Object>}}
     */
    function textGroup(raw, hint) {
        return {type: 'text', group: null, key: grammar.TEXT_KEY, items: [{
            kind: 'text', key: grammar.TEXT_KEY, value: raw, label: raw, hint: hint, count: null
        }]};
    }

    /**
     * @param {string} raw
     * @param {Array<Object>} chips
     * @param {{keys: Array<Object>, lists: Object<string, Array<Object>>}} vocabulary
     * @param {Object} limits
     * @returns {Array<Object>}
     */
    function words(raw, chips, vocabulary, limits) {
        var groups = [],
            query = lower(raw),
            keep = function (item) {
                return unused(chips)(item) && contains(query)(item);
            },
            searchable = vocabulary.keys.filter(function (description) {
                return description.key !== grammar.TEXT_KEY;
            });

        if (raw === '') {
            push(groups, {type: 'keys', group: null, key: null}, searchable.map(keyItem), limits.allKeys);

            return groups;
        }

        push(groups, {type: 'keys', group: null, key: null}, searchable.filter(function (description) {
            return description.key.indexOf(query) === 0 || lower(description.label).indexOf(query) === 0;
        }).map(keyItem), limits.keys);

        WORD_GROUPS.forEach(function (code) {
            var items = [];

            searchable.forEach(function (description) {
                if (description.group === code) {
                    items = items.concat(valuesOf(vocabulary, description.key, keep));
                }
            });
            push(groups, {type: 'group', group: code, key: null}, items, limits[code]);
        });

        groups.push(textGroup(raw, 'anywhere'));

        return groups;
    }

    /**
     * Offers for what was typed
     *
     * @param {string} input What the search input holds
     * @param {Array<{key: string, value: string}>} chips The chips already in the bar
     * @param {{keys: Array<{key: string, label: string, hint: string, group: string}>,
     *     lists: Object<string, Array<Object>>}} vocabulary The parsed suggestion lists
     * @param {Object=} limits Items per group: keys, allKeys, matching and one per suggestion group code
     * @returns {Array<{type: string, group: ?string, key: ?string, items: Array<Object>}>} type "keys", "group",
     *     "matching", "text" or "typed"; items are {kind: "key"|"value"|"text"|"typed", key, value, label, hint, count}
     */
    function suggest(input, chips, vocabulary, limits) {
        var raw = String(input || '').trim(),
            caps = Object.assign({}, LIMITS, limits || {}),
            keyed = grammar.split(raw),
            description,
            groups = [];

        if (keyed === null) {
            return words(raw, chips, vocabulary, caps);
        }

        description = vocabulary.keys.filter(function (candidate) {
            return candidate.key === keyed.key;
        })[0];
        if (description === undefined && keyed.key !== grammar.TEXT_KEY) {
            return [textGroup(raw, 'unknownKey')];
        }
        description = description || {key: keyed.key, label: keyed.key, hint: ''};

        push(
            groups,
            {type: 'matching', group: null, key: keyed.key},
            valuesOf(vocabulary, keyed.key, function (item) {
                return unused(chips)(item) && contains(lower(keyed.rest))(item);
            }),
            caps.matching
        );

        if (keyed.rest !== '') {
            groups.push({type: 'typed', group: null, key: keyed.key, items: [{
                kind: 'typed', key: keyed.key, value: keyed.rest, label: keyed.rest, hint: description.hint, count: null
            }]});
        }

        return groups;
    }

    /**
     * The items of the groups in display order, for moving through them with the arrow keys
     *
     * @param {Array<{items: Array<Object>}>} groups
     * @returns {Array<Object>}
     */
    function flatten(groups) {
        return groups.reduce(function (items, group) {
            return items.concat(group.items);
        }, []);
    }

    /**
     * What choosing an item does: a key continues the typing as "key: ", anything else becomes a chip through the
     * grammar, so a chosen item obeys the same rules as typed text
     *
     * @param {{kind: string, key: string, value: string}} item
     * @param {Array<string>} knownKeys
     * @returns {{type: string, input: ?string, result: ?{ok: boolean, chip: ?Object, error: ?string}}}
     *     type "continue" with the input to show, or "chip" with the grammar's result
     */
    function choose(item, knownKeys) {
        if (item.kind === 'key') {
            return {type: 'continue', input: item.key + ': ', result: null};
        }

        return {type: 'chip', input: null, result: grammar.parse(item.key + ': ' + item.value, knownKeys)};
    }

    return Object.freeze({
        LIMITS: LIMITS,
        WORD_GROUPS: WORD_GROUPS,
        suggest: suggest,
        flatten: flatten,
        choose: choose
    });
});
