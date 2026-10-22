/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The filters panel as chips, both ways.
 *
 * A control is {key, select, options: [{value, target}], companion}: one search key, as a text box or a select. A
 * select may have a companion text box whose value is searched under the key its chosen option names (its target):
 * with an option naming the key "alpha" chosen, "42" typed beside it becomes "alpha: 42". Applying follows these
 * rules, control by control in panel order:
 * - a value in a companion box becomes a chip of the chosen option's target key, or plain text when the option names
 *   no key or none is chosen;
 * - else a chosen select option, or a text box's value, becomes a chip of the control's key;
 * - a blank control adds nothing.
 * Only the controls the admin changed count: a control still showing what the chips filled it with keeps its chips,
 * all of them (two chips of one key stay two). A changed control's chip replaces the chips of every key it holds (its
 * key and its options' targets); the other chips stay, the changed controls' chips follow. Showing the chips in the
 * panel takes, for a select with a companion box, the first chip of a target key (with the first option naming that
 * key), else the first chip of the select's own key. Plain words stay in the search bar. Pure.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/grammar'
], function (grammar) {
    'use strict';

    /**
     * @param {{options: Array<{value: string, target: string}>}} control
     * @returns {Array<string>} The keys its options name
     */
    function targetsOf(control) {
        return control.companion ? control.options.map(function (option) {
            return option.target;
        }).filter(function (target) {
            return target !== '';
        }) : [];
    }

    /**
     * @param {Array<{key: string, value: string}>} chips
     * @param {function({key: string}): boolean} wanted
     * @returns {?{key: string, value: string}}
     */
    function first(chips, wanted) {
        return chips.filter(wanted)[0] || null;
    }

    /**
     * What the panel's controls show for the chips
     *
     * @param {Array<{key: string, value: string}>} chips
     * @param {Array<Object>} controls
     * @returns {{fields: Object<string, string>, companions: Object<string, string>}} Every control's key, "" for
     *     nothing
     */
    function fill(chips, controls) {
        var values = {fields: {}, companions: {}};

        controls.forEach(function (control) {
            var targets = targetsOf(control),
                targeted = first(chips, function (chip) {
                    return targets.indexOf(chip.key) !== -1;
                }),
                own = first(chips, function (chip) {
                    return chip.key === control.key;
                });

            values.fields[control.key] = own === null ? '' : own.value;
            if (!control.companion) {
                return;
            }
            values.companions[control.key] = '';
            if (targeted !== null) {
                values.fields[control.key] = control.options.filter(function (option) {
                    return option.target === targeted.key;
                })[0].value;
                values.companions[control.key] = targeted.value;
            }
        });

        return values;
    }

    /**
     * @param {Object} control
     * @param {{fields: Object<string, string>, companions: Object<string, string>}} values
     * @returns {?{key: string, value: string}} The chip one control stands for
     */
    function chipOf(control, values) {
        var chosen = String(values.fields[control.key] || '').trim(),
            typed = control.companion ? String(values.companions[control.key] || '').trim() : '',
            option;

        if (typed !== '') {
            option = control.options.filter(function (candidate) {
                return candidate.value === chosen;
            })[0];

            return {key: option && option.target !== '' ? option.target : grammar.TEXT_KEY, value: typed};
        }

        return chosen === '' ? null : {key: control.key, value: chosen};
    }

    /**
     * @param {*} value
     * @returns {string}
     */
    function trimmed(value) {
        return String(value === undefined || value === null ? '' : value).trim();
    }

    /**
     * @param {Object} control
     * @param {{fields: Object<string, string>, companions: Object<string, string>}} values What the panel holds
     * @param {{fields: Object<string, string>, companions: Object<string, string>}} shown What the chips filled in
     * @returns {boolean} Whether the admin changed the control (or its companion box) in the panel
     */
    function isChanged(control, values, shown) {
        return trimmed(values.fields[control.key]) !== trimmed(shown.fields[control.key])
            || (control.companion
                && trimmed(values.companions[control.key]) !== trimmed(shown.companions[control.key]));
    }

    /**
     * The chips after the panel is applied
     *
     * Errors: "tooLong" (a value over grammar.MAX_VALUE_LENGTH), "limit" (more than grammar.MAX_CHIPS chips).
     *
     * @param {Array<{key: string, value: string}>} chips
     * @param {{fields: Object<string, string>, companions: Object<string, string>}} values
     * @param {Array<Object>} controls
     * @returns {{ok: boolean, chips: Array<{key: string, value: string}>, error: ?string}} the chips unchanged on
     *     an error
     */
    function apply(chips, values, controls) {
        var shown = fill(chips, controls),
            changed = controls.filter(function (control) {
                return isChanged(control, values, shown);
            }),
            held = [],
            result,
            error = null;

        changed.forEach(function (control) {
            held = held.concat([control.key], targetsOf(control));
        });
        result = chips.filter(function (chip) {
            return held.indexOf(chip.key) === -1;
        });

        changed.forEach(function (control) {
            var chip = error === null ? chipOf(control, values) : null,
                added;

            if (chip === null) {
                return;
            }
            if (Array.from(chip.value).length > grammar.MAX_VALUE_LENGTH) {
                error = 'tooLong';

                return;
            }
            added = grammar.add(result, chip);
            if (!added.ok) {
                error = added.error;

                return;
            }
            result = added.chips;
        });

        return error === null
            ? {ok: true, chips: result, error: null}
            : {ok: false, chips: chips.slice(), error: error};
    }

    return Object.freeze({
        fill: fill,
        apply: apply
    });
});
