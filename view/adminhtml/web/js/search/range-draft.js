/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The range popover while it is open: the preset or the typed dates and minutes, the month the calendar shows, and
 * which end the next day picked sets.
 *
 * The draft starts from the range in use. Choosing a preset fills the inputs with its range; typing in an input or
 * picking a day in the calendar makes the draft "custom". Apply turns the draft into a range (a preset's range is
 * computed again at that moment); Cancel drops it. Pure: now and the store's timezone are passed in, wording too.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/range',
    'Hryvinskyi_ActivityLog/js/search/calendar'
], function (range, calendar) {
    'use strict';

    var INPUTS = Object.freeze(['fromDate', 'fromTime', 'toDate', 'toTime']),
        DATE_KEY = /^(\d{4})-(\d{2})-(\d{2})$/;

    /**
     * @param {Object} draft
     * @param {Object} changes
     * @returns {Object} A frozen copy of the draft with the changes
     */
    function update(draft, changes) {
        return Object.freeze(Object.assign({}, draft, changes));
    }

    /**
     * @param {string} key "YYYY-MM-DD"
     * @returns {?{year: number, month: number, day: number}}
     */
    function dayOf(key) {
        var match = DATE_KEY.exec(String(key || ''));

        return match === null ? null : {year: Number(match[1]), month: Number(match[2]), day: Number(match[3])};
    }

    /**
     * @param {{from: number, to: number}} bounds
     * @param {Object} zone
     * @returns {Object} The inputs and the calendar month of the range's start
     */
    function inputsOf(bounds, zone) {
        var inputs = range.draft(bounds, zone),
            start = dayOf(inputs.fromDate);

        return {
            fromDate: inputs.fromDate,
            fromTime: inputs.fromTime,
            toDate: inputs.toDate,
            toTime: inputs.toTime,
            year: start.year,
            month: start.month
        };
    }

    /**
     * The draft of the range in use, as the popover opens
     *
     * @param {{preset: string, from: number, to: number}} current
     * @param {Object} zone
     * @returns {Object} {preset, fromDate, fromTime, toDate, toTime, year, month, picking: "from"|"to"}
     */
    function open(current, zone) {
        return update(inputsOf(current, zone), {preset: current.preset, picking: 'from'});
    }

    /**
     * The draft after a preset was chosen
     *
     * @param {Object} draft
     * @param {string} code A range code, or range.CUSTOM
     * @param {number} nowMs
     * @param {Object} zone
     * @returns {Object}
     */
    function choosePreset(draft, code, nowMs, zone) {
        var chosen = code === range.CUSTOM ? null : range.preset(code, nowMs, zone);

        if (chosen === null || !chosen.ok) {
            return update(draft, {preset: range.CUSTOM});
        }

        return update(draft, Object.assign(inputsOf(chosen.range, zone), {preset: code, picking: 'from'}));
    }

    /**
     * The draft after one of its inputs was typed in
     *
     * @param {Object} draft
     * @param {string} name "fromDate", "fromTime", "toDate" or "toTime"
     * @param {string} value
     * @returns {Object} The draft unchanged for another name
     */
    function type(draft, name, value) {
        var changes = {preset: range.CUSTOM};

        if (INPUTS.indexOf(name) === -1) {
            return draft;
        }
        changes[name] = String(value);

        return update(draft, changes);
    }

    /**
     * The draft after a day was picked in the calendar
     *
     * @param {Object} draft
     * @param {string} key "YYYY-MM-DD"
     * @param {string} today "YYYY-MM-DD" in the store's timezone
     * @returns {Object}
     */
    function pickDay(draft, key, today) {
        var picked = calendar.pick({from: draft.fromDate, to: draft.toDate, picking: draft.picking}, key, today);

        if (picked.from === draft.fromDate && picked.to === draft.toDate && picked.picking === draft.picking) {
            return draft;
        }

        return update(draft, {preset: range.CUSTOM, fromDate: picked.from, toDate: picked.to, picking: picked.picking});
    }

    /**
     * The draft with the calendar moved by some months
     *
     * @param {Object} draft
     * @param {number} delta
     * @returns {Object}
     */
    function shiftMonth(draft, delta) {
        var shifted = calendar.shift(draft.year, draft.month, delta);

        return update(draft, {year: shifted.year, month: shifted.month});
    }

    /**
     * The range the draft stands for
     *
     * @param {Object} draft
     * @param {number} nowMs
     * @param {Object} zone
     * @returns {{ok: boolean, range: ?{preset: string, from: number, to: number}, error: ?string}} errors as
     *     range.custom returns them
     */
    function apply(draft, nowMs, zone) {
        return draft.preset === range.CUSTOM
            ? range.custom(draft, zone)
            : range.preset(draft.preset, nowMs, zone);
    }

    /**
     * @param {number} ms
     * @param {Object} zone
     * @returns {string} "YYYY-MM-DD" of that instant in the store's timezone
     */
    function dayKey(ms, zone) {
        var wall = zone.wallTime(ms);

        return String(wall.year) + '-' + (wall.month < 10 ? '0' : '') + wall.month + '-'
            + (wall.day < 10 ? '0' : '') + wall.day;
    }

    /**
     * @param {string} key
     * @param {Object} zone
     * @returns {number} Noon of that day in the store's timezone, an instant safely inside the day
     */
    function noonOf(key, zone) {
        var day = dayOf(key);

        return zone.instant({year: day.year, month: day.month, day: day.day, hour: 12, minute: 0});
    }

    /**
     * What the popover shows for a draft: the presets with their current ranges, and the calendar month
     *
     * @param {Object} draft
     * @param {number} nowMs
     * @param {Object} zone
     * @param {{presets: Object<string, string>, pickDates: string, calendarFrom: string, calendarTo: string}} wording
     * @returns {{presets: Array<{code: string, label: string, detail: string, active: boolean}>, monthLabel: string,
     *     weekdays: Array<string>, canGoNext: boolean, hint: string, days: Array<Object>}} days as calendar.month
     *     returns them, each with "label", its date written out
     */
    function view(draft, nowMs, zone, wording) {
        var today = dayKey(nowMs, zone),
            month = calendar.month(draft.year, draft.month, {today: today, from: draft.fromDate, to: draft.toDate}),
            presets = range.CODES.map(function (code) {
                var described = range.describe(range.preset(code, nowMs, zone).range, zone, wording);

                return {
                    code: code,
                    label: described.label,
                    detail: described.from + ' – ' + described.to,
                    active: draft.preset === code
                };
            });

        presets.push({
            code: range.CUSTOM,
            label: wording.presets[range.CUSTOM],
            detail: wording.pickDates,
            active: draft.preset === range.CUSTOM
        });

        return {
            presets: presets,
            monthLabel: zone.format(noonOf(month.days[7].key, zone), 'month'),
            weekdays: month.days.slice(0, 7).map(function (day) {
                return zone.format(noonOf(day.key, zone), 'weekday');
            }),
            canGoNext: month.canGoNext,
            hint: draft.picking === 'from' ? wording.calendarFrom : wording.calendarTo,
            days: month.days.map(function (day) {
                return Object.assign({label: zone.format(noonOf(day.key, zone), 'date')}, day);
            })
        };
    }

    return Object.freeze({
        open: open,
        choosePreset: choosePreset,
        type: type,
        pickDay: pickDay,
        shiftMonth: shiftMonth,
        apply: apply,
        dayKey: dayKey,
        view: view
    });
});
