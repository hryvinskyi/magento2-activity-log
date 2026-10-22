/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * How the search bar's chips and offers read: each line of the list of offers as a key column, a text and a hint,
 * and each chip as its key and value, all in the server's wording.
 *
 * - A group is titled by its type: "Filter by", the title of its suggestion group, "Matching <key>", "Text" or
 *   "Use as typed".
 * - An offer's hint carries how often the value occurs, when the server counted it ("Admin · 12").
 * - Values of keys that name fields (setting paths, field codes) are set in a monospaced font.
 * - A chip the grammar refused is explained with its bound ("A search takes at most 20 filters").
 * Pure: takes suggest.js groups, returns data with each offer's position in suggest.flatten order.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/wording',
    'Hryvinskyi_ActivityLog/js/search/grammar'
], function (wording, grammar) {
    'use strict';

    /**
     * Title of a group by its type
     */
    var TITLES = Object.freeze({
            keys: function (group, words) {
                return words.filterBy;
            },
            group: function (group, words) {
                return words.groups[group.group] || '';
            },
            matching: function (group, words) {
                return wording.fill(words.matching, group.key);
            },
            text: function (group, words) {
                return words.textGroup;
            },
            typed: function (group, words) {
                return words.typed;
            }
        }),

        /**
         * One offer's line by its kind
         */
        LINES = Object.freeze({
            key: function (item) {
                return {keyLabel: item.key + ':', text: item.label, hint: item.hint, mono: false};
            },
            value: function (item, words, monoKeys, formatNumber) {
                return {
                    keyLabel: item.key + ':',
                    text: item.label,
                    hint: [item.hint, item.count === null ? '' : formatNumber(item.count)].filter(function (part) {
                        return part !== '';
                    }).join(' · '),
                    mono: monoKeys.indexOf(item.key) !== -1
                };
            },
            text: function (item, words) {
                return {
                    keyLabel: words.textKey,
                    text: wording.fill(words.quoted, item.value),
                    hint: words[item.hint] || '',
                    mono: false
                };
            },
            typed: function (item, words, monoKeys) {
                return {
                    keyLabel: item.key + ':',
                    text: item.key + ': ' + item.value,
                    hint: item.hint,
                    mono: monoKeys.indexOf(item.key) !== -1
                };
            }
        });

    /**
     * Keys whose values name fields
     *
     * @param {Array<{key: string, matchesFieldNames: boolean}>} keys
     * @returns {Array<string>}
     */
    function monoKeys(keys) {
        return keys.filter(function (description) {
            return description.matchesFieldNames === true;
        }).map(function (description) {
            return description.key;
        });
    }

    /**
     * The list of offers as it reads
     *
     * @param {Array<{type: string, group: ?string, key: ?string, items: Array<Object>}>} groups From suggest.suggest
     * @param {Object} words The page wording
     * @param {Array<{key: string, matchesFieldNames: boolean}>} keys The key descriptions
     * @param {function(number): string} formatNumber
     * @returns {Array<{title: string, items: Array<{index: number, keyLabel: string, text: string, hint: string,
     *     mono: boolean}>}>}
     */
    function offers(groups, words, keys, formatNumber) {
        var mono = monoKeys(keys),
            index = 0;

        return groups.map(function (group) {
            return {
                title: TITLES[group.type](group, words),
                items: group.items.map(function (item) {
                    var line = LINES[item.kind](item, words, mono, formatNumber);

                    line.index = index;
                    index += 1;

                    return line;
                })
            };
        });
    }

    /**
     * The chips as they read
     *
     * @param {Array<{key: string, value: string}>} chips
     * @param {Object} words The page wording
     * @param {Array<{key: string, matchesFieldNames: boolean}>} keys The key descriptions
     * @returns {Array<{keyLabel: string, value: string, mono: boolean, plain: boolean, removeLabel: string}>}
     *     plain for words searched as text, which show no key
     */
    function chips(chips, words, keys) {
        var mono = monoKeys(keys);

        return chips.map(function (chip) {
            var plain = chip.key === grammar.TEXT_KEY;

            return {
                keyLabel: plain ? '' : chip.key + ':',
                value: chip.value,
                mono: mono.indexOf(chip.key) !== -1,
                plain: plain,
                removeLabel: wording.fill(words.removeChip, chip.key + ': ' + chip.value)
            };
        });
    }

    /**
     * Why a chip was not added, in the server's wording
     *
     * @param {?string} code An error code of the grammar ("limit", "tooLong"); "empty" and null say nothing
     * @param {Object} words The page wording
     * @returns {string} Empty when there is nothing to say
     */
    function refusal(code, words) {
        var bounds = {limit: grammar.MAX_CHIPS, tooLong: grammar.MAX_VALUE_LENGTH};

        return code !== null && Object.prototype.hasOwnProperty.call(bounds, code)
            ? wording.fill(words[code], bounds[code])
            : '';
    }

    return Object.freeze({
        offers: offers,
        chips: chips,
        refusal: refusal
    });
});
