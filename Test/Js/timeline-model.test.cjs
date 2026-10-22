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

const utc = (...parts) => Date.UTC(...parts);
const copenhagen = () => load('search/zone').create('Europe/Copenhagen', 'en_GB');
const formatNumber = (value) => value.toLocaleString('en-US');

const WORDS = {
    atLeast: '%1+', showingOf: 'Showing %1 of %2', changeOne: 'change', changeMany: 'changes', events: 'events',
    outsideOne: '%1 matching change is outside the selected range. The latest is from %2.',
    outsideMany: '%1 matching changes are outside the selected range. The latest is from %2.',
    noMatch: 'Nothing matches all of these filters.', nothingInRange: 'Nothing was recorded in this range.',
    narrow: 'Only the newest %1 were searched.', loadMore: 'Load %1 more', loadedOf: 'Loaded %1 of %2',
    dayEventsOne: '%1 event', dayEventsMany: '%1 events'
};

const search = (overrides = {}) => Object.assign({
    chips: [],
    range: {preset: '7d', from: utc(2026, 9, 16), to: utc(2026, 9, 22, 12)},
    changesOnly: true
}, overrides);

test('days: an activity belongs to the store\'s calendar day, not the browser\'s or UTC\'s', () => {
    const days = load('search/days');
    const zone = copenhagen();

    assert.equal(days.key(utc(2026, 9, 21, 22, 30), zone), '2026-10-22', '00:30 in Copenhagen is the next day');
    assert.equal(days.key(utc(2026, 9, 21, 21, 59), zone), '2026-10-21');
    assert.equal(days.key(utc(2026, 9, 24, 23, 30), zone), '2026-10-25', 'summer time still applies on Oct 25 01:30');
    assert.equal(days.key(utc(2026, 9, 25, 23, 30), zone), '2026-10-26', 'winter time: UTC+1');
});

test('days: today and yesterday are the store\'s, across midnight and the clock change', () => {
    const days = load('search/days');
    const zone = copenhagen();
    const justAfterMidnight = utc(2026, 9, 21, 22, 5);

    assert.equal(days.relation(utc(2026, 9, 21, 22, 1), justAfterMidnight, zone), 'today');
    assert.equal(days.relation(utc(2026, 9, 21, 21, 59), justAfterMidnight, zone), 'yesterday',
        'a minute before the store\'s midnight is yesterday');
    assert.equal(days.relation(utc(2026, 9, 20, 21, 59), justAfterMidnight, zone), 'earlier');
    assert.equal(days.relation(utc(2026, 9, 24, 23, 30), utc(2026, 9, 25, 23, 30), zone), 'yesterday',
        'the day the clocks go back is 25 hours long and still one day');
});

test('days: activities group by day in the order they came, ready to continue with the next page', () => {
    const days = load('search/days');
    const events = [
        {id: 3, occurredAtMs: utc(2026, 9, 22, 10)},
        {id: 2, occurredAtMs: utc(2026, 9, 22, 8)},
        {id: 1, occurredAtMs: utc(2026, 9, 20, 9)}
    ];

    assert.deepEqual(plain(days.group(events, copenhagen())), [
        {key: '2026-10-22', firstMs: utc(2026, 9, 22, 10), events: [events[0], events[1]]},
        {key: '2026-10-20', firstMs: utc(2026, 9, 20, 9), events: [events[2]]}
    ]);
    assert.deepEqual(plain(days.group([], copenhagen())), []);
});

test('days: how long ago is told in the largest unit it fits, and a time ahead of now is now', () => {
    const days = load('search/days');
    const now = utc(2026, 9, 22, 12);

    assert.deepEqual(plain(days.ago(now - 42000, now)), {value: -42, unit: 'second'});
    assert.deepEqual(plain(days.ago(now - 90000, now)), {value: -1, unit: 'minute'});
    assert.deepEqual(plain(days.ago(now - 5 * 3600000, now)), {value: -5, unit: 'hour'});
    assert.deepEqual(plain(days.ago(now - 3 * 86400000, now)), {value: -3, unit: 'day'});
    assert.deepEqual(plain(days.ago(now - 70 * 86400000, now)), {value: -2, unit: 'month'});
    assert.deepEqual(plain(days.ago(now - 800 * 86400000, now)), {value: -2, unit: 'year'});
    assert.deepEqual(plain(days.ago(now + 5000, now)), {value: 0, unit: 'second'});
});

test('results: the state of a first page — results, an empty log, or no match', () => {
    const results = load('timeline/results');

    assert.equal(results.stateOf({events: [{}], journalEmpty: false}), 'results');
    assert.equal(results.stateOf({events: [], journalEmpty: true}), 'emptyLog');
    assert.equal(results.stateOf({events: [], journalEmpty: false}), 'noResults');
});

test('results: the count line is exact when everything is shown, "Showing" when more is to load, bounded past the cap', () => {
    const results = load('timeline/results');

    assert.deepEqual(plain(results.head(search(), {count: 184, atLeast: false}, 184, WORDS, formatNumber)),
        {count: '184', noun: 'changes'});
    assert.deepEqual(plain(results.head(search(), {count: 1, atLeast: false}, 1, WORDS, formatNumber)),
        {count: '1', noun: 'change'});
    assert.deepEqual(plain(results.head(search(), {count: 184302, atLeast: false}, 50, WORDS, formatNumber)),
        {count: 'Showing 50 of 184,302', noun: 'changes'});
    assert.deepEqual(plain(results.head(search({changesOnly: false}), {count: 10000, atLeast: true}, 50, WORDS,
        formatNumber)), {count: 'Showing 50 of 10,000+', noun: 'events'});
    assert.deepEqual(plain(results.head(search(), null, 3, WORDS, formatNumber)), {count: '3', noun: 'changes'},
        'without a total the loaded activities are counted');
});

test('results: no match in the range names the matches outside it and offers the ways out', () => {
    const results = load('timeline/results');
    const at = (ms) => 'at ' + ms;
    const wider = {from: utc(2026, 8, 23), to: utc(2026, 9, 22, 12)};
    const latest = utc(2026, 9, 10);
    const outside = {outside: {count: 3, atLeast: false}, latestOutsideMs: latest};

    assert.deepEqual(plain(results.noResults(search({chips: [{key: 'by', value: 'john'}]}), outside, WORDS,
        formatNumber, at, wider)), {
        hint: '3 matching changes are outside the selected range. The latest is from at ' + latest + '.',
        widen: true,
        removeLast: true,
        clearAll: true
    });
    assert.equal(results.noResults(search(), {outside: {count: 1, atLeast: false}, latestOutsideMs: 5}, WORDS,
        formatNumber, at, wider).hint, '1 matching change is outside the selected range. The latest is from at 5.');
    assert.equal(results.noResults(search(), {outside: {count: 10000, atLeast: true}, latestOutsideMs: 5}, WORDS,
        formatNumber, at, wider).hint,
    '10,000+ matching changes are outside the selected range. The latest is from at 5.');
    assert.deepEqual(plain(results.noResults(search(), {outside: null, latestOutsideMs: null}, WORDS, formatNumber,
        at, wider)), {hint: 'Nothing was recorded in this range.', widen: false, removeLast: false, clearAll: false});
    assert.deepEqual(plain(results.noResults(search({chips: [{key: 'text', value: 'x'}]}),
        {outside: {count: 0, atLeast: false}, latestOutsideMs: null}, WORDS, formatNumber, at, wider)),
    {hint: 'Nothing matches all of these filters.', widen: false, removeLast: true, clearAll: true});
});

test('results: widening is offered only when the last 30 days would show the latest match outside the range', () => {
    const results = load('timeline/results');
    const widen = (range, latestOutsideMs, wider = {from: utc(2026, 8, 23), to: utc(2026, 9, 22, 12)}) =>
        results.noResults(search({range: range}), {outside: {count: 2, atLeast: false}, latestOutsideMs},
            WORDS, formatNumber, String, wider).widen;
    const week = {preset: '7d', from: utc(2026, 9, 16), to: utc(2026, 9, 22, 12)};

    assert.equal(widen(week, utc(2026, 9, 1)), true, 'a week, and the latest match three weeks ago');
    assert.equal(widen(week, utc(2026, 7, 1)), false, 'the latest match is older than 30 days: widening shows nothing');
    assert.equal(widen({preset: '30d', from: utc(2026, 8, 23), to: utc(2026, 9, 22, 12)}, utc(2026, 7, 1)), false,
        'the last 30 days are not widened to themselves');
    assert.equal(widen({preset: 'custom', from: utc(2026, 5, 1), to: utc(2026, 8, 1)}, utc(2026, 9, 10)), false,
        'a range longer than 30 days is not made shorter');
    assert.equal(widen({preset: 'custom', from: utc(2026, 8, 1), to: utc(2026, 8, 3)}, utc(2026, 9, 10)), true,
        'two days in September, and the latest match last week');
    assert.equal(widen(week, utc(2026, 9, 1), null), false, 'nothing to widen to');
});

test('results: the narrow notice, the paging line and the day counts', () => {
    const results = load('timeline/results');

    assert.equal(results.narrow({narrowed: true}, 10000, WORDS, formatNumber), 'Only the newest 10,000 were searched.');
    assert.equal(results.narrow({narrowed: false}, 10000, WORDS, formatNumber), null);
    assert.deepEqual(plain(results.paging('abc', {count: 184302, atLeast: false}, 50, 50, WORDS, formatNumber)),
        {label: 'Load 50 more', note: 'Loaded 50 of 184,302'});
    assert.equal(results.paging(null, {count: 3, atLeast: false}, 3, 50, WORDS, formatNumber), null);
    assert.equal(results.dayCount(1, WORDS, formatNumber), '1 event');
    assert.equal(results.dayCount(1204, WORDS, formatNumber), '1,204 events');
    assert.equal(results.WIDEN_TO, '30d');
});

/**
 * A timeline config as the assets print it
 */
function timelineConfig(overrides = {}) {
    const wording = {};

    load('timeline/config').WORDING.forEach((name) => {
        wording[name] = name;
    });

    return Object.assign({
        wording: wording,
        verbs: {changed: 'changed', created: 'created', deleted: 'deleted', performed: 'performed',
            tried_to_change: 'tried to change', tried_to_delete: 'tried to delete'},
        channels: {records: {label: 'Record change', chart: '#000', bg: '#eee', fg: '#111'}},
        actorTypes: {admin: 'Admin'},
        outcomes: {success: 'Success', failure: 'Failure', denied: 'Denied'},
        candidateLimit: 10000
    }, overrides);
}

test('timeline config: the printed config is checked before the timeline writes a word of it', () => {
    const config = load('timeline/config');
    const parsed = plain(config.parse(timelineConfig()));

    assert.equal(parsed.verbs.tried_to_delete, 'tried to delete');
    assert.deepEqual(parsed.channels, {records: {label: 'Record change', bg: '#eee', fg: '#111'}});
    assert.equal(parsed.candidateLimit, 10000);

    const wording = timelineConfig().wording;
    delete wording.loadMore;
    assert.throws(() => config.parse(timelineConfig({wording: wording})), /"loadMore" is missing/);
    assert.throws(() => config.parse(timelineConfig({verbs: {changed: 'changed'}})), /verb "created" is missing/);
    assert.throws(() => config.parse(timelineConfig({outcomes: {success: 'Success'}})), /outcome "failure"/);
    assert.throws(() => config.parse(timelineConfig({candidateLimit: 0})), /candidate limit/);
    assert.throws(() => config.parse(timelineConfig({actorTypes: {admin: 7}})), /not text/);
    assert.throws(() => config.parse(null), /not an object/);
});

test('page state: a filter asked for alone replaces the chips and keeps the range', () => {
    const state = load('search/search-state');
    const before = {
        chips: [{key: 'by', value: 'john'}, {key: 'text', value: 'price'}],
        range: {preset: '30d', from: 1, to: 2},
        changesOnly: true,
        view: 'timeline'
    };
    const context = {keys: ['by', 'request', 'text'], changeChannels: ['records'], channels: {}};

    const alone = state.withCriterion(before, {key: 'request', value: 'c7a1e9', alone: true}, context);
    assert.equal(alone.ok, true);
    assert.deepEqual(plain(alone.state.chips), [{key: 'request', value: 'c7a1e9'}]);
    assert.deepEqual(plain(alone.state.range), {preset: '30d', from: 1, to: 2});
    assert.equal(alone.state.changesOnly, false, 'a whole request includes activities without changes');

    const added = state.withCriterion(before, {key: 'by', value: 'jane'}, context);
    assert.deepEqual(plain(added.state.chips), [{key: 'by', value: 'john'}, {key: 'text', value: 'price'},
        {key: 'by', value: 'jane'}], 'without "alone" the filter is added');
});
