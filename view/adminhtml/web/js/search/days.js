/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Where an activity sits in time for the people reading the log: the store's calendar day it belongs to, whether that
 * day is today or yesterday, and how long ago it happened
 *
 * Days are the store's (see zone.js), not the browser's: an activity at 23:30 in the store's timezone belongs to that
 * day for every admin, wherever their browser is. Results arrive newest first, so their days arrive in order too.
 * Pure: now and the timezone are passed in.
 */
define([], function () {
    'use strict';

    var SECOND_MS = 1000,
        MINUTE_MS = 60 * SECOND_MS,
        HOUR_MS = 60 * MINUTE_MS,
        DAY_MS = 24 * HOUR_MS,

        /**
         * The unit "how long ago" is told in, by the largest distance it still fits; the last one takes the rest
         */
        UNITS = Object.freeze([
            {unit: 'second', ms: SECOND_MS, below: MINUTE_MS},
            {unit: 'minute', ms: MINUTE_MS, below: HOUR_MS},
            {unit: 'hour', ms: HOUR_MS, below: DAY_MS},
            {unit: 'day', ms: DAY_MS, below: 30 * DAY_MS},
            {unit: 'month', ms: 30 * DAY_MS, below: 365 * DAY_MS},
            {unit: 'year', ms: 365 * DAY_MS, below: Infinity}
        ]);

    /**
     * @param {number} value
     * @param {number} width
     * @returns {string}
     */
    function pad(value, width) {
        return String(value).padStart(width, '0');
    }

    /**
     * @param {number} year
     * @param {number} month 1–12
     * @param {number} day
     * @returns {string}
     */
    function keyOf(year, month, day) {
        return pad(year, 4) + '-' + pad(month, 2) + '-' + pad(day, 2);
    }

    /**
     * The store's calendar day of an instant
     *
     * @param {number} ms
     * @param {{wallTime: function(number): Object}} zone
     * @returns {string} "YYYY-MM-DD"
     */
    function key(ms, zone) {
        var wall = zone.wallTime(ms);

        return keyOf(wall.year, wall.month, wall.day);
    }

    /**
     * Whether an instant falls on the store's today, its yesterday, or earlier
     *
     * @param {number} ms
     * @param {number} nowMs
     * @param {{wallTime: function(number): Object}} zone
     * @returns {string} "today", "yesterday" or "earlier" ("today" also for a time after now on today's date)
     */
    function relation(ms, nowMs, zone) {
        var today = zone.wallTime(nowMs),
            before = new Date(Date.UTC(today.year, today.month - 1, today.day - 1)),
            day = key(ms, zone);

        if (day === keyOf(today.year, today.month, today.day)) {
            return 'today';
        }

        return day === keyOf(before.getUTCFullYear(), before.getUTCMonth() + 1, before.getUTCDate())
            ? 'yesterday'
            : 'earlier';
    }

    /**
     * Activities grouped by the store's day, in the order they came
     *
     * @param {Array<{occurredAtMs: number}>} events Newest first
     * @param {{wallTime: function(number): Object}} zone
     * @returns {Array<{key: string, firstMs: number, events: Array<Object>}>} firstMs is the time of the group's
     *     first activity, to write the day's name from
     */
    function group(events, zone) {
        var groups = [];

        events.forEach(function (event) {
            var day = key(event.occurredAtMs, zone),
                last = groups[groups.length - 1];

            if (last !== undefined && last.key === day) {
                last.events.push(event);

                return;
            }
            groups.push({key: day, firstMs: event.occurredAtMs, events: [event]});
        });

        return groups;
    }

    /**
     * How long ago an instant was, as Intl.RelativeTimeFormat takes it
     *
     * @param {number} ms
     * @param {number} nowMs
     * @returns {{value: number, unit: string}} value 0 or less; a time after now (a clock running ahead) is "now"
     */
    function ago(ms, nowMs) {
        var distance = Math.max(0, nowMs - ms),
            step = UNITS.filter(function (candidate) {
                return distance < candidate.below;
            })[0];

        return {value: 0 - Math.floor(distance / step.ms), unit: step.unit};
    }

    return Object.freeze({
        key: key,
        relation: relation,
        group: group,
        ago: ago
    });
});
