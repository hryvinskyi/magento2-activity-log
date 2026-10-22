/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const {load} = require('./amd.cjs');

/** Values built inside the module's own realm, as plain data of this one */
const plain = (value) => JSON.parse(JSON.stringify(value));

const HOUR = 3600000;
const utc = (...parts) => Date.UTC(...parts);
const copenhagen = () => load('search/zone').create('Europe/Copenhagen', 'en_GB');

test('clock: now is the browser clock plus the skew to the server, read on every call', () => {
    const clock = load('search/clock');
    let browser = 1000000;
    const now = clock.create(1600000, 1000000, () => browser);

    assert.equal(now.skewMs, 600000);
    assert.equal(now.now(), 1600000);
    browser += HOUR;
    assert.equal(now.now(), 1600000 + HOUR, 'a page left open an hour asks for the hour that ends now');

    const skewed = clock.create(utc(2026, 9, 22, 12, 45), utc(2026, 9, 22, 10, 45), () => utc(2026, 9, 22, 11, 0));
    assert.deepEqual(plain(load('search/range').preset('hour', skewed.now(), copenhagen()).range),
        {preset: 'hour', from: utc(2026, 9, 22, 12, 0), to: utc(2026, 9, 22, 13, 0)},
        'a browser two hours slow still asks for the server\'s last hour');

    const behind = clock.create(1000000, 1600000, () => 1600000);
    assert.equal(behind.now(), 1000000, 'a browser clock ahead of the server is corrected too');

    assert.throws(() => clock.create('1600000', 1000000, () => 0), {name: 'TypeError'});
    assert.throws(() => clock.create(1600000, NaN, () => 0), {name: 'TypeError'});
    assert.throws(() => clock.create(1600000, 1000000, null), {name: 'TypeError'});
});

test('zone: wall time and offsets across both DST changes in Europe/Copenhagen', () => {
    const zone = copenhagen();

    assert.deepEqual(plain(zone.wallTime(utc(2026, 9, 22, 12, 45, 0, 410))), {
        year: 2026, month: 10, day: 22, hour: 14, minute: 45, second: 0, millisecond: 410, weekday: 4
    });
    assert.equal(zone.offsetAt(utc(2026, 2, 29, 0, 59)), HOUR, 'winter time before 01:00 UTC on Mar 29');
    assert.equal(zone.offsetAt(utc(2026, 2, 29, 1, 0)), 2 * HOUR, 'summer time from 01:00 UTC on Mar 29');
    assert.equal(zone.offsetAt(utc(2026, 9, 25, 0, 59)), 2 * HOUR);
    assert.equal(zone.offsetAt(utc(2026, 9, 25, 1, 0)), HOUR, 'winter time again from 01:00 UTC on Oct 25');
});

test('zone: a skipped local time moves forward, a repeated one takes the earlier instant', () => {
    const zone = copenhagen();

    assert.equal(zone.instant({year: 2026, month: 3, day: 29, hour: 2, minute: 30}), utc(2026, 2, 29, 1, 30),
        '02:30 does not exist on Mar 29: it is read as 03:30 summer time');
    assert.equal(zone.instant({year: 2026, month: 3, day: 29, hour: 3, minute: 0}), utc(2026, 2, 29, 1, 0));
    assert.equal(zone.instant({year: 2026, month: 3, day: 29, hour: 1, minute: 59}), utc(2026, 2, 29, 0, 59));
    assert.equal(zone.instant({year: 2026, month: 10, day: 25, hour: 2, minute: 30}), utc(2026, 9, 25, 0, 30),
        '02:30 happens twice on Oct 25: the summer-time one comes first');
    assert.equal(zone.instant({year: 2026, month: 10, day: 25, hour: 3, minute: 0}), utc(2026, 9, 25, 2, 0));
    assert.equal(zone.instant({year: 2026, month: 1, day: 1, hour: 0, minute: 0}), utc(2025, 11, 31, 23, 0));
});

test('zone: dates are written in the admin locale; bad names fail loudly', () => {
    const zoneModule = load('search/zone');
    const english = zoneModule.create('Europe/Copenhagen', 'en_GB');
    const danish = zoneModule.create('Europe/Copenhagen', 'da_DK');
    const instant = utc(2026, 9, 22, 12, 45);

    assert.equal(english.format(instant, 'time'), '14:45', 'the store time, not the machine time');
    assert.notEqual(danish.format(instant, 'day'), english.format(instant, 'day'));
    assert.match(english.format(instant, 'long'), /2026/);
    assert.equal(zoneModule.create('America/New_York', 'en_GB').format(instant, 'time'), '08:45');

    assert.throws(() => english.format(instant, 'fancy'), {name: 'ZoneError'});
    assert.throws(() => zoneModule.create('Mars/Olympus', 'en_GB'), {name: 'ZoneError'});
    assert.throws(() => zoneModule.create('Europe/Copenhagen', 'not a locale!'), {name: 'ZoneError'});
});

test('range: presets at an ordinary moment', () => {
    const range = load('search/range');
    const zone = copenhagen();
    const now = utc(2026, 9, 22, 12, 45);

    assert.deepEqual(plain(range.preset('hour', now, zone).range), {preset: 'hour', from: now - HOUR, to: now});
    assert.deepEqual(plain(range.preset('today', now, zone).range),
        {preset: 'today', from: utc(2026, 9, 21, 22, 0), to: now});
    assert.deepEqual(plain(range.preset('yesterday', now, zone).range),
        {preset: 'yesterday', from: utc(2026, 9, 20, 22, 0), to: utc(2026, 9, 21, 22, 0) - 1});
    assert.deepEqual(plain(range.preset('7d', now, zone).range),
        {preset: '7d', from: utc(2026, 9, 15, 22, 0), to: now});
    assert.deepEqual(plain(range.preset('30d', now, zone).range),
        {preset: '30d', from: utc(2026, 8, 22, 22, 0), to: now});
    assert.deepEqual(plain(range.preset('90d', now, zone)), {ok: false, range: null, error: 'unknownPreset'});
    assert.deepEqual(plain(range.CODES), ['hour', 'today', 'yesterday', '7d', '30d']);
    assert.equal(range.DEFAULT_PRESET, '7d');
});

test('range: "Yesterday" just before and just after the store\'s midnight', () => {
    const range = load('search/range');
    const zone = copenhagen();
    const midnight = utc(2026, 9, 22, 22, 0);

    assert.deepEqual(plain(range.preset('yesterday', midnight - 1, zone).range), {
        preset: 'yesterday', from: utc(2026, 9, 20, 22, 0), to: utc(2026, 9, 21, 22, 0) - 1
    }, 'at 23:59:59.999 on Oct 22, yesterday is Oct 21');
    assert.deepEqual(plain(range.preset('yesterday', midnight, zone).range), {
        preset: 'yesterday', from: utc(2026, 9, 21, 22, 0), to: midnight - 1
    }, 'at 00:00 on Oct 23, yesterday is Oct 22');
    assert.equal(range.preset('today', midnight, zone).range.from, midnight);
    assert.equal(range.preset('today', midnight - 1, zone).range.from, utc(2026, 9, 21, 22, 0),
        'UTC already says Oct 22 22:00, the store still says Oct 22');
});

test('range: days that are 23 and 25 hours long', () => {
    const range = load('search/range');
    const zone = copenhagen();

    const spring = range.preset('yesterday', utc(2026, 2, 30, 10, 0), zone).range;
    assert.equal(spring.from, utc(2026, 2, 28, 23, 0));
    assert.equal(spring.to - spring.from + 1, 23 * HOUR, 'Mar 29 had 23 hours');

    const autumn = range.preset('yesterday', utc(2026, 9, 26, 11, 0), zone).range;
    assert.equal(autumn.from, utc(2026, 9, 24, 22, 0), 'Oct 25 began in summer time');
    assert.equal(autumn.to, utc(2026, 9, 25, 23, 0) - 1, 'and ended in winter time');
    assert.equal(autumn.to - autumn.from + 1, 25 * HOUR);

    const today = range.preset('today', utc(2026, 2, 29, 10, 0), zone).range;
    assert.equal(today.from, utc(2026, 2, 28, 23, 0), 'midnight of the spring-forward day is still winter time');

    assert.equal(range.lastDays(30, utc(2026, 10, 10, 11, 0), zone).from, utc(2026, 9, 11, 22, 0),
        'thirty days back across the change to winter time starts at a summer-time midnight');
    assert.throws(() => range.lastDays(0, 0, zone), {name: 'RangeError'});
});

test('range: works with any timezone port, here one fixed at UTC+3', () => {
    const range = load('search/range');
    const offset = 3 * HOUR;
    const fixed = {
        wallTime: (ms) => {
            const shifted = new Date(ms + offset);
            return {
                year: shifted.getUTCFullYear(), month: shifted.getUTCMonth() + 1, day: shifted.getUTCDate(),
                hour: shifted.getUTCHours(), minute: shifted.getUTCMinutes()
            };
        },
        instant: (wall) => Date.UTC(wall.year, wall.month - 1, wall.day, wall.hour, wall.minute) - offset,
        format: (ms, style) => style + ':' + ms
    };

    assert.equal(range.preset('today', utc(2026, 0, 1, 22, 0), fixed).range.from, utc(2026, 0, 1, 21, 0));
    assert.deepEqual(plain(range.describe({preset: 'hour', from: 1, to: 2}, fixed, {presets: {hour: 'H'}})),
        {label: 'H', from: 'short:1', to: 'short:2'});
});

test('range: custom ranges from dates and minutes', () => {
    const range = load('search/range');
    const zone = copenhagen();

    assert.deepEqual(plain(range.custom({fromDate: '2026-10-01', fromTime: '09:00', toDate: '2026-10-22', toTime: '14:45'}, zone)), {
        ok: true,
        range: {preset: 'custom', from: utc(2026, 9, 1, 7, 0), to: utc(2026, 9, 22, 12, 46) - 1},
        error: null
    }, 'the "to" minute is included to its last millisecond');

    const wholeDays = range.custom({fromDate: '2026-10-21', fromTime: '', toDate: '2026-10-21', toTime: null}, zone).range;
    assert.equal(wholeDays.from, utc(2026, 9, 20, 22, 0));
    assert.equal(wholeDays.to, utc(2026, 9, 21, 22, 0) - 1);

    const swapped = range.custom({fromDate: '2026-10-22', fromTime: '14:00', toDate: '2026-10-22', toTime: '13:00'}, zone).range;
    assert.equal(swapped.from, utc(2026, 9, 22, 11, 0));
    assert.equal(swapped.to, utc(2026, 9, 22, 12, 1) - 1);

    assert.equal(range.custom({fromDate: '2026-03-29', fromTime: '02:30', toDate: '2026-03-29', toTime: '02:30'}, zone).range.from,
        utc(2026, 2, 29, 1, 30), 'a skipped minute moves forward');
    assert.equal(range.custom({fromDate: '2026-10-25', fromTime: '02:30', toDate: '2026-10-25', toTime: '02:30'}, zone).range.from,
        utc(2026, 9, 25, 0, 30), 'a repeated minute takes the earlier instant');

    assert.equal(range.custom({fromDate: '2026-02-30', fromTime: '', toDate: '2026-03-01', toTime: ''}, zone).error, 'fromDate');
    assert.equal(range.custom({fromDate: '2026-02-01', fromTime: '24:00', toDate: '2026-03-01', toTime: ''}, zone).error, 'fromTime');
    assert.equal(range.custom({fromDate: '2026-02-01', fromTime: '', toDate: '1.3.2026', toTime: ''}, zone).error, 'toDate');
    assert.equal(range.custom({fromDate: '2026-02-01', fromTime: '', toDate: '2026-03-01', toTime: '9:5'}, zone).error, 'toTime');
});

test('range: inputs, labels, link tokens and moving with now', () => {
    const range = load('search/range');
    const zone = copenhagen();
    const now = utc(2026, 9, 22, 12, 45);
    const week = range.preset('7d', now, zone).range;

    assert.deepEqual(plain(range.draft(week, zone)),
        {fromDate: '2026-10-16', fromTime: '00:00', toDate: '2026-10-22', toTime: '14:45'});

    const wording = {presets: {hour: 'Last hour', '7d': 'Last 7 days', custom: 'Custom'}};
    const described = range.describe(week, zone, wording);
    assert.equal(described.label, 'Last 7 days');
    assert.equal(described.from, zone.format(week.from, 'short'));
    assert.equal(range.describe(week, zone, wording, 'long').to, zone.format(now, 'long'));
    assert.throws(() => range.describe(week, zone, {presets: {}}), {name: 'RangeError'});

    assert.equal(range.toToken(week), '7d');
    const custom = {preset: 'custom', from: 1000, to: 5000};
    assert.equal(range.toToken(custom), '1000-5000');
    assert.deepEqual(plain(range.fromToken('1000-5000', now, zone).range), custom);
    assert.deepEqual(plain(range.fromToken('30d', now, zone).range), plain(range.preset('30d', now, zone).range));
    assert.equal(range.fromToken('5000-1000', now, zone).error, 'unknownRange');
    assert.equal(range.fromToken('forever', now, zone).error, 'unknownRange');
    assert.equal(range.fromToken(null, now, zone).error, 'unknownRange');

    assert.deepEqual(plain(range.refresh(week, now + HOUR, zone)), {preset: '7d', from: week.from, to: now + HOUR});
    assert.deepEqual(plain(range.refresh(custom, now + HOUR, zone)), custom);
});

test('calendar: six weeks from the week of the first day, future days and the picked range marked', () => {
    const calendar = load('search/calendar');
    const october = calendar.month(2026, 10, {today: '2026-10-22', from: '2026-10-16', to: '2026-10-22'});

    assert.equal(october.days.length, 42);
    assert.equal(october.days[0].key, '2026-09-27', 'Oct 1, 2026 is a Thursday; the Sunday week starts Sep 27');
    assert.equal(october.days[0].inMonth, false);
    assert.equal(october.days[4].key, '2026-10-01');
    const day = (key) => october.days.find((candidate) => candidate.key === key);
    assert.equal(day('2026-10-22').isToday, true);
    assert.equal(day('2026-10-23').isFuture, true);
    assert.equal(day('2026-10-16').isFrom, true);
    assert.equal(day('2026-10-19').inRange, true);
    assert.equal(day('2026-10-22').inRange, false, 'the ends are marked as ends');
    assert.equal(october.canGoNext, false);
    assert.equal(calendar.month(2026, 9, {today: '2026-10-22'}).canGoNext, true);

    const mondays = calendar.month(2026, 10, {today: '2026-10-22', weekStart: 1});
    assert.equal(mondays.days[0].key, '2026-09-28');

    assert.deepEqual(plain(calendar.shift(2026, 1, -1)), {year: 2025, month: 12});
    assert.deepEqual(plain(calendar.shift(2026, 12, 1)), {year: 2027, month: 1});
});

test('calendar: the first pick sets the start, the second the end', () => {
    const calendar = load('search/calendar');
    const today = '2026-10-22';
    let state = {from: '2026-10-16', to: '2026-10-22', picking: 'from'};

    state = calendar.pick(state, '2026-10-05', today);
    assert.deepEqual(plain(state), {from: '2026-10-05', to: '2026-10-22', picking: 'to'});
    state = calendar.pick(state, '2026-10-09', today);
    assert.deepEqual(plain(state), {from: '2026-10-05', to: '2026-10-09', picking: 'from'});

    const restart = calendar.pick({from: '2026-10-05', to: '2026-10-09', picking: 'to'}, '2026-10-01', today);
    assert.deepEqual(plain(restart), {from: '2026-10-01', to: '2026-10-09', picking: 'to'}, 'a day before the start');

    const later = calendar.pick({from: '2026-10-05', to: '2026-10-09', picking: 'from'}, '2026-10-12', today);
    assert.deepEqual(plain(later), {from: '2026-10-12', to: '2026-10-12', picking: 'to'});

    const future = calendar.pick({from: '2026-10-05', to: '2026-10-09', picking: 'to'}, '2026-10-30', today);
    assert.deepEqual(plain(future), {from: '2026-10-05', to: '2026-10-09', picking: 'to'});
});
