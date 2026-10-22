/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The store's timezone: wall-clock time of an instant, the instant of a wall-clock time, and dates written in the
 * admin's locale
 *
 * Built on Intl.DateTimeFormat with a timeZone, which every supported browser ships with its own timezone data: no
 * library, no request, and the same answers Node gives the tests. A wall-clock time that does not exist (the hour
 * skipped when clocks go forward) moves forward by the length of the gap; one that happens twice (the hour repeated
 * when clocks go back) takes the earlier instant. Pure: no clock is read.
 */
define([], function () {
    'use strict';

    var DAY_MS = 86400000,
        PARTS = ['year', 'month', 'day', 'hour', 'minute', 'second'],

        /**
         * How each style writes a time; the locale decides the order, words and separators. "clock" is the
         * 24-hour time of an activity, to which the timeline adds the milliseconds.
         */
        STYLES = Object.freeze({
            short: {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'},
            long: {year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'},
            date: {year: 'numeric', month: 'short', day: 'numeric'},
            time: {hour: '2-digit', minute: '2-digit'},
            clock: {hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'},
            day: {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'},
            month: {year: 'numeric', month: 'long'},
            weekday: {weekday: 'short'}
        });

    /**
     * @param {string} message
     * @param {*=} cause
     * @constructor
     */
    function ZoneError(message, cause) {
        this.name = 'ZoneError';
        this.message = message;
        this.cause = cause;
    }

    ZoneError.prototype = Object.create(Error.prototype);

    /**
     * @param {string} locale A Magento locale ("da_DK") or a language tag ("da-DK")
     * @returns {string}
     */
    function languageTag(locale) {
        return String(locale || '').replace(/_/g, '-');
    }

    /**
     * @param {number} ms
     * @returns {number} The millisecond within its second, also before 1970
     */
    function millisecondOf(ms) {
        return ((ms % 1000) + 1000) % 1000;
    }

    /**
     * @param {{year: number, month: number, day: number, hour: number, minute: number, second: ?number,
     *     millisecond: ?number}} wall
     * @returns {number} The wall-clock time read as if it were UTC
     */
    function asUtc(wall) {
        return Date.UTC(wall.year, wall.month - 1, wall.day, wall.hour, wall.minute, wall.second || 0,
            wall.millisecond || 0);
    }

    /**
     * The store's timezone in the admin's locale
     *
     * @param {string} timeZone An IANA name, such as "Europe/Copenhagen"
     * @param {string} locale The admin's interface locale
     * @returns {Object} {timeZone, wallTime(ms), offsetAt(ms), instant(wall), format(ms, style)}
     * @throws {ZoneError} When the timezone or the locale is unknown
     */
    function create(timeZone, locale) {
        var tag = languageTag(locale),
            formatters = {},
            reader;

        try {
            reader = new Intl.DateTimeFormat('en-US-u-nu-latn', {
                timeZone: timeZone,
                hourCycle: 'h23',
                year: 'numeric',
                month: 'numeric',
                day: 'numeric',
                hour: 'numeric',
                minute: 'numeric',
                second: 'numeric'
            });
            Intl.DateTimeFormat.supportedLocalesOf([tag]);
        } catch (failure) {
            throw new ZoneError('Unknown timezone "' + timeZone + '" or locale "' + locale + '"', failure);
        }

        /**
         * @param {number} ms
         * @returns {{year: number, month: number, day: number, hour: number, minute: number, second: number,
         *     millisecond: number, weekday: number}} Month 1–12, weekday 0 (Sunday)–6
         */
        function wallTime(ms) {
            var wall = {};

            reader.formatToParts(ms).forEach(function (part) {
                if (PARTS.indexOf(part.type) !== -1) {
                    wall[part.type] = Number(part.value);
                }
            });
            wall.hour = wall.hour % 24;
            wall.millisecond = millisecondOf(ms);
            wall.weekday = new Date(Date.UTC(wall.year, wall.month - 1, wall.day)).getUTCDay();

            return wall;
        }

        /**
         * @param {number} ms
         * @returns {number} How far the wall clock is ahead of UTC at that instant, in ms
         */
        function offsetAt(ms) {
            return asUtc(wallTime(ms)) - ms;
        }

        /**
         * @param {{year: number, month: number, day: number, hour: number, minute: number, second: ?number,
         *     millisecond: ?number}} wall Month 1–12
         * @returns {number} The instant the wall clock shows that time; see the module notes for gaps and overlaps
         */
        function instant(wall) {
            var naive = asUtc(wall),
                before = naive - offsetAt(naive - DAY_MS),
                after = naive - offsetAt(naive + DAY_MS),
                beforeFits = offsetAt(before) === naive - before,
                afterFits = offsetAt(after) === naive - after;

            if (beforeFits && afterFits) {
                return Math.min(before, after);
            }
            if (afterFits) {
                return after;
            }

            return before;
        }

        /**
         * @param {number} ms
         * @param {string} style One of STYLES
         * @returns {string}
         * @throws {ZoneError} On an unknown style
         */
        function format(ms, style) {
            if (!Object.prototype.hasOwnProperty.call(STYLES, style)) {
                throw new ZoneError('Unknown date style "' + style + '"');
            }
            if (!formatters[style]) {
                formatters[style] = new Intl.DateTimeFormat(tag, Object.assign({timeZone: timeZone}, STYLES[style]));
            }

            return formatters[style].format(ms);
        }

        return Object.freeze({
            timeZone: timeZone,
            wallTime: wallTime,
            offsetAt: offsetAt,
            instant: instant,
            format: format
        });
    }

    return Object.freeze({
        STYLES: STYLES,
        create: create,
        ZoneError: ZoneError
    });
});
