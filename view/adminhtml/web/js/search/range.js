/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The time range of a search, in the store's timezone
 *
 * A range is {preset, from, to}: ms since the epoch, both ends included. Presets are relative to now:
 * - "hour": the last 60 minutes;
 * - "today": from the store's midnight to now;
 * - "yesterday": the whole previous calendar day;
 * - "7d", "30d": the last N days, which means from the store's midnight N−1 days ago to now (the server counts "the
 *   last N days" with the same rule, so a count it prints matches the list this range finds).
 * "custom" is typed as dates and minutes: its "to" minute is included up to its last millisecond.
 *
 * Pure: now and the timezone (see zone.js) are passed in; labels come from the wording passed in.
 */
define([], function () {
    'use strict';

    var HOUR_MS = 3600000,
        MINUTE_MS = 60000,
        CUSTOM = 'custom',
        DEFAULT_PRESET = '7d',
        DATE = /^(\d{4})-(\d{2})-(\d{2})$/,
        TIME = /^([01]\d|2[0-3]):([0-5]\d)$/,
        CUSTOM_TOKEN = /^(\d{1,15})-(\d{1,15})$/,
        PRESETS,
        CODES;

    /**
     * @param {Object} zone
     * @param {number} nowMs
     * @param {number} daysBack 0 for today
     * @returns {number} The store's midnight that many calendar days before today
     */
    function midnight(zone, nowMs, daysBack) {
        var today = zone.wallTime(nowMs),
            day = new Date(Date.UTC(today.year, today.month - 1, today.day - daysBack));

        return zone.instant({
            year: day.getUTCFullYear(),
            month: day.getUTCMonth() + 1,
            day: day.getUTCDate(),
            hour: 0,
            minute: 0
        });
    }

    /**
     * "The last N days": from the store's midnight N−1 days ago to now
     *
     * @param {number} days 1 or more
     * @param {number} nowMs
     * @param {Object} zone
     * @returns {{from: number, to: number}}
     */
    function lastDays(days, nowMs, zone) {
        if (typeof days !== 'number' || Math.floor(days) !== days || days < 1) {
            throw new RangeError('"The last N days" needs N of 1 or more');
        }

        return {from: midnight(zone, nowMs, days - 1), to: nowMs};
    }

    PRESETS = Object.freeze({
        hour: function (nowMs) {
            return {from: nowMs - HOUR_MS, to: nowMs};
        },
        today: function (nowMs, zone) {
            return lastDays(1, nowMs, zone);
        },
        yesterday: function (nowMs, zone) {
            return {from: midnight(zone, nowMs, 1), to: midnight(zone, nowMs, 0) - 1};
        },
        '7d': function (nowMs, zone) {
            return lastDays(7, nowMs, zone);
        },
        '30d': function (nowMs, zone) {
            return lastDays(30, nowMs, zone);
        }
    });
    CODES = Object.freeze(Object.keys(PRESETS));

    /**
     * @param {string} code
     * @returns {boolean}
     */
    function isPreset(code) {
        return Object.prototype.hasOwnProperty.call(PRESETS, code);
    }

    /**
     * A preset's range at this moment
     *
     * @param {string} code One of CODES
     * @param {number} nowMs
     * @param {Object} zone
     * @returns {{ok: boolean, range: ?{preset: string, from: number, to: number}, error: ?string}}
     *     error "unknownPreset"
     */
    function preset(code, nowMs, zone) {
        var bounds;

        if (!isPreset(code)) {
            return {ok: false, range: null, error: 'unknownPreset'};
        }
        bounds = PRESETS[code](nowMs, zone);

        return {ok: true, range: {preset: code, from: bounds.from, to: bounds.to}, error: null};
    }

    /**
     * The range brought up to date: a preset moves with now, a custom range stays as typed
     *
     * @param {{preset: string, from: number, to: number}} range
     * @param {number} nowMs
     * @param {Object} zone
     * @returns {{preset: string, from: number, to: number}}
     */
    function refresh(range, nowMs, zone) {
        return isPreset(range.preset) ? preset(range.preset, nowMs, zone).range : range;
    }

    /**
     * @param {string} value "YYYY-MM-DD"
     * @returns {?{year: number, month: number, day: number}} Null when it is not a calendar date
     */
    function dateOf(value) {
        var match = DATE.exec(String(value || '')),
            day;

        if (match === null) {
            return null;
        }
        day = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));

        return day.getUTCFullYear() === Number(match[1]) && day.getUTCMonth() === Number(match[2]) - 1
            && day.getUTCDate() === Number(match[3])
            ? {year: Number(match[1]), month: Number(match[2]), day: Number(match[3])}
            : null;
    }

    /**
     * @param {string} value "HH:MM", 24-hour
     * @returns {?{hour: number, minute: number}}
     */
    function timeOf(value) {
        var match = TIME.exec(String(value || ''));

        return match === null ? null : {hour: Number(match[1]), minute: Number(match[2])};
    }

    /**
     * The start of a typed minute
     *
     * @param {string} date
     * @param {string} time
     * @param {string} fallbackTime Used when no time is typed
     * @param {string} which "from" or "to"
     * @param {Object} zone
     * @returns {{ok: boolean, ms: ?number, error: ?string}}
     */
    function minuteOf(date, time, fallbackTime, which, zone) {
        var day = dateOf(date),
            clock = timeOf(time === '' || time === undefined || time === null ? fallbackTime : time);

        if (day === null) {
            return {ok: false, ms: null, error: which + 'Date'};
        }
        if (clock === null) {
            return {ok: false, ms: null, error: which + 'Time'};
        }

        return {
            ok: true,
            ms: zone.instant({year: day.year, month: day.month, day: day.day, hour: clock.hour, minute: clock.minute}),
            error: null
        };
    }

    /**
     * A custom range from the popover's inputs
     *
     * A missing time means the whole day (00:00 to 23:59). Ends typed the wrong way round are swapped, as the
     * calendar lets the admin pick either day first. Errors: "fromDate", "fromTime", "toDate", "toTime".
     *
     * @param {{fromDate: string, fromTime: ?string, toDate: string, toTime: ?string}} draft
     * @param {Object} zone
     * @returns {{ok: boolean, range: ?{preset: string, from: number, to: number}, error: ?string}}
     */
    function custom(draft, zone) {
        var from = minuteOf(draft.fromDate, draft.fromTime, '00:00', 'from', zone),
            to = from.ok ? minuteOf(draft.toDate, draft.toTime, '23:59', 'to', zone) : from,
            first,
            last;

        if (!to.ok) {
            return {ok: false, range: null, error: to.error};
        }
        first = Math.min(from.ms, to.ms);
        last = Math.max(from.ms, to.ms);

        return {ok: true, range: {preset: CUSTOM, from: first, to: last + MINUTE_MS - 1}, error: null};
    }

    /**
     * @param {number} value
     * @returns {string}
     */
    function pad(value) {
        return (value < 10 ? '0' : '') + value;
    }

    /**
     * @param {Object} zone
     * @param {number} ms
     * @returns {{date: string, time: string}}
     */
    function inputsAt(zone, ms) {
        var wall = zone.wallTime(ms);

        return {
            date: String(wall.year) + '-' + pad(wall.month) + '-' + pad(wall.day),
            time: pad(wall.hour) + ':' + pad(wall.minute)
        };
    }

    /**
     * The popover's inputs for a range, to edit it
     *
     * @param {{from: number, to: number}} range
     * @param {Object} zone
     * @returns {{fromDate: string, fromTime: string, toDate: string, toTime: string}}
     */
    function draft(range, zone) {
        var from = inputsAt(zone, range.from),
            to = inputsAt(zone, range.to);

        return {fromDate: from.date, fromTime: from.time, toDate: to.date, toTime: to.time};
    }

    /**
     * How the range reads: the preset's name and both ends in the admin's locale
     *
     * @param {{preset: string, from: number, to: number}} range
     * @param {Object} zone
     * @param {{presets: Object<string, string>}} wording Names of the presets and of "custom"
     * @param {string=} style A zone.js style, "short" unless given
     * @returns {{label: string, from: string, to: string}}
     */
    function describe(range, zone, wording, style) {
        var label = wording && wording.presets ? wording.presets[range.preset] : undefined;

        if (typeof label !== 'string') {
            throw new RangeError('No wording for the range "' + range.preset + '"');
        }

        return {
            label: label,
            from: zone.format(range.from, style || 'short'),
            to: zone.format(range.to, style || 'short')
        };
    }

    /**
     * The range as a link carries it: a preset's code, or "from-to" in ms for a custom range
     *
     * @param {{preset: string, from: number, to: number}} range
     * @returns {string}
     */
    function toToken(range) {
        return isPreset(range.preset) ? range.preset : String(range.from) + '-' + String(range.to);
    }

    /**
     * The range a link asked for
     *
     * @param {?string} token
     * @param {number} nowMs
     * @param {Object} zone
     * @returns {{ok: boolean, range: ?{preset: string, from: number, to: number}, error: ?string}}
     *     error "unknownRange"
     */
    function fromToken(token, nowMs, zone) {
        var match = CUSTOM_TOKEN.exec(String(token || '')),
            from,
            to;

        if (isPreset(String(token))) {
            return preset(String(token), nowMs, zone);
        }
        if (match === null) {
            return {ok: false, range: null, error: 'unknownRange'};
        }
        from = Number(match[1]);
        to = Number(match[2]);

        return from <= to
            ? {ok: true, range: {preset: CUSTOM, from: from, to: to}, error: null}
            : {ok: false, range: null, error: 'unknownRange'};
    }

    return Object.freeze({
        CODES: CODES,
        CUSTOM: CUSTOM,
        DEFAULT_PRESET: DEFAULT_PRESET,
        lastDays: lastDays,
        preset: preset,
        refresh: refresh,
        custom: custom,
        draft: draft,
        describe: describe,
        toToken: toToken,
        fromToken: fromToken
    });
});
