/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The range popover's calendar: the six weeks shown for a month, and picking a start day then an end day
 *
 * Days are calendar dates written "YYYY-MM-DD", which sort as text. Days after today cannot be picked: nothing has
 * been recorded there yet. Pure: today comes from the caller, in the store's timezone.
 */
define([], function () {
    'use strict';

    var DAYS_SHOWN = 42;

    /**
     * @param {number} value
     * @returns {string}
     */
    function pad(value) {
        return (value < 10 ? '0' : '') + value;
    }

    /**
     * @param {Date} day A date at UTC midnight
     * @returns {string}
     */
    function keyOf(day) {
        return String(day.getUTCFullYear()) + '-' + pad(day.getUTCMonth() + 1) + '-' + pad(day.getUTCDate());
    }

    /**
     * @param {number} year
     * @param {number} month 1–12
     * @param {number} delta Months to move, negative for earlier
     * @returns {{year: number, month: number}}
     */
    function shift(year, month, delta) {
        var first = new Date(Date.UTC(year, month - 1 + delta, 1));

        return {year: first.getUTCFullYear(), month: first.getUTCMonth() + 1};
    }

    /**
     * The days of a month as the calendar shows them, from the week of its first day on
     *
     * @param {number} year
     * @param {number} month 1–12
     * @param {{today: string, from: ?string, to: ?string, weekStart: ?number}} marks weekStart 0 (Sunday)–6
     * @returns {{year: number, month: number, canGoNext: boolean, days: Array<{key: string, day: number,
     *     inMonth: boolean, isToday: boolean, isFuture: boolean, isFrom: boolean, isTo: boolean,
     *     inRange: boolean}>}}
     */
    function month(year, month, marks) {
        var first = new Date(Date.UTC(year, month - 1, 1)),
            weekStart = marks.weekStart || 0,
            lead = (first.getUTCDay() - weekStart + 7) % 7,
            next = shift(year, month, 1),
            days = [],
            index,
            day,
            key;

        for (index = 0; index < DAYS_SHOWN; index += 1) {
            day = new Date(Date.UTC(year, month - 1, 1 - lead + index));
            key = keyOf(day);
            days.push({
                key: key,
                day: day.getUTCDate(),
                inMonth: day.getUTCMonth() === month - 1,
                isToday: key === marks.today,
                isFuture: key > marks.today,
                isFrom: key === marks.from,
                isTo: key === marks.to,
                inRange: Boolean(marks.from && marks.to) && key > marks.from && key < marks.to
            });
        }

        return {
            year: year,
            month: month,
            canGoNext: String(next.year) + '-' + pad(next.month) + '-01' <= marks.today,
            days: days
        };
    }

    /**
     * Picking a day: the first pick sets the start, the next one the end; a day before the start restarts
     *
     * @param {{from: ?string, to: ?string, picking: string}} state picking "from" or "to"
     * @param {string} key The day picked
     * @param {string} today
     * @returns {{from: ?string, to: ?string, picking: string}} The state unchanged for a future day
     */
    function pick(state, key, today) {
        if (key > today) {
            return {from: state.from, to: state.to, picking: state.picking};
        }
        if (state.picking === 'from' || !state.from || key < state.from) {
            return {from: key, to: state.to && state.to >= key ? state.to : key, picking: 'to'};
        }

        return {from: state.from, to: key, picking: 'from'};
    }

    return Object.freeze({
        shift: shift,
        month: month,
        pick: pick
    });
});
