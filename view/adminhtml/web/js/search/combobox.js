/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The search bar as a combobox: what the input holds, whether the list of offers is open, which offer is active, and
 * what each key does.
 *
 * - ↑ and ↓ open the list and move through the offers, stopping at either end;
 * - Enter takes the active offer while the list shows one, else the typed text as it is;
 * - Backspace in an empty input removes the last chip;
 * - Esc closes the list.
 * Typing opens the list with its first offer active, so Enter takes the best offer. Pure: the page passes the
 * number of offers it shows; what an intent does is the page's.
 */
define([], function () {
    'use strict';

    /**
     * What each key asks for; keys missing here are left to the input
     */
    var KEYS = Object.freeze({
        ArrowDown: function (state, count) {
            return {type: 'move', state: moved(state, 1, count)};
        },
        ArrowUp: function (state, count) {
            return {type: 'move', state: moved(state, -1, count)};
        },
        Enter: function (state, count) {
            if (state.open && state.active >= 0 && state.active < count) {
                return {type: 'choose', state: state};
            }

            return state.input.trim() === '' ? null : {type: 'commit', state: state};
        },
        Backspace: function (state, count, hasChips) {
            return state.input === '' && hasChips ? {type: 'removeLast', state: state} : null;
        },
        Escape: function (state) {
            return state.open ? {type: 'close', state: closed(state)} : null;
        }
    });

    /**
     * @param {string} input
     * @param {boolean} open
     * @param {number} active
     * @returns {{input: string, open: boolean, active: number}}
     */
    function stateOf(input, open, active) {
        return Object.freeze({input: input, open: open, active: active});
    }

    /**
     * @returns {{input: string, open: boolean, active: number}} An empty, closed bar
     */
    function initial() {
        return stateOf('', false, -1);
    }

    /**
     * @param {{input: string}} state
     * @returns {{input: string, open: boolean, active: number}}
     */
    function closed(state) {
        return stateOf(state.input, false, -1);
    }

    /**
     * @param {{input: string, open: boolean, active: number}} state
     * @param {number} delta
     * @param {number} count Offers shown
     * @returns {{input: string, open: boolean, active: number}}
     */
    function moved(state, delta, count) {
        var next;

        if (count === 0) {
            return stateOf(state.input, true, -1);
        }
        next = !state.open || state.active < 0 ? 0 : state.active + delta;

        return stateOf(state.input, true, Math.max(0, Math.min(count - 1, next)));
    }

    /**
     * The bar after the admin typed, or after an offer continued the typing ("key: "): the list opens on its first
     * offer
     *
     * @param {string} input
     * @returns {{input: string, open: boolean, active: number}}
     */
    function typed(input) {
        return stateOf(String(input), true, 0);
    }

    /**
     * The bar with the list open, as when the input gets focus
     *
     * @param {{input: string, open: boolean, active: number}} state
     * @returns {{input: string, open: boolean, active: number}}
     */
    function opened(state) {
        return state.open ? state : stateOf(state.input, true, 0);
    }

    /**
     * The bar with an offer made active, as when the pointer rests on it
     *
     * @param {{input: string}} state
     * @param {number} index
     * @param {number} count
     * @returns {{input: string, open: boolean, active: number}}
     */
    function hovered(state, index, count) {
        return index >= 0 && index < count ? stateOf(state.input, true, index) : state;
    }

    /**
     * @returns {{input: string, open: boolean, active: number}} The bar after a chip was added: empty and closed
     */
    function cleared() {
        return initial();
    }

    /**
     * What a key press asks for
     *
     * @param {string} key KeyboardEvent.key
     * @param {{input: string, open: boolean, active: number}} state
     * @param {number} count Offers shown
     * @param {boolean} hasChips
     * @returns {?{type: string, state: Object}} type "move", "choose", "commit", "removeLast" or "close"; null
     *     when the key is the input's own
     */
    function keyDown(key, state, count, hasChips) {
        return Object.prototype.hasOwnProperty.call(KEYS, key) ? KEYS[key](state, count, hasChips) : null;
    }

    return Object.freeze({
        initial: initial,
        typed: typed,
        opened: opened,
        closed: closed,
        hovered: hovered,
        cleared: cleared,
        keyDown: keyDown
    });
});
