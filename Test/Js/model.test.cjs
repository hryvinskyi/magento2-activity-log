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

const SCALE = [
    {key: 'good', below: 100, color: '#3C9D5A', text: '#1E7B3C'},
    {key: 'slow', below: 1000, color: '#E6A117', text: '#7A5A00'},
    {key: 'critical', below: null, color: '#C8372D', text: '#B0251D'}
];

test('operation groups: rows of one operation shown together go oldest first under their trigger', () => {
    const groups = load('model/operation-groups');
    const rows = [
        {entity_id: '9', correlation_id: 'solo', occurred_at_ms: '5000', channel: 'rest'},
        {entity_id: '3', correlation_id: 'op1', occurred_at_ms: '1081', channel: 'entity'},
        {entity_id: '2', correlation_id: 'op1', occurred_at_ms: '1038', channel: 'entity'},
        {entity_id: '1', correlation_id: 'op1', occurred_at_ms: '1000', channel: 'mcp'},
        {entity_id: '8', correlation_id: 'other', occurred_at_ms: '900', channel: 'admin'}
    ];

    const arranged = groups.arrange(rows);

    assert.deepEqual(plain(arranged.map((row) => row.entity_id)), ['9', '1', '2', '3', '8']);
    assert.equal(arranged[0].operation, null);
    assert.deepEqual(plain(arranged[1].operation), {position: 'first', size: 3, triggerChannel: 'mcp', offsetMs: 0});
    assert.deepEqual(plain(arranged[2].operation), {position: 'middle', size: 3, triggerChannel: 'mcp', offsetMs: 38});
    assert.deepEqual(plain(arranged[3].operation), {position: 'last', size: 3, triggerChannel: 'mcp', offsetMs: 81});
    assert.equal(rows[1].operation, undefined, 'the input rows are not changed');
});

test('operation groups: rows of one operation split by another row are not joined', () => {
    const groups = load('model/operation-groups');

    const arranged = groups.arrange([
        {entity_id: '1', correlation_id: 'op1', occurred_at_ms: '1'},
        {entity_id: '2', correlation_id: 'op2', occurred_at_ms: '2'},
        {entity_id: '3', correlation_id: 'op1', occurred_at_ms: '3'}
    ]);

    assert.ok(arranged.every((row) => row.operation === null));
});

test('duration scale: boundaries and missing durations', () => {
    const scale = load('model/duration-scale');

    assert.equal(scale.levelOf('99.9', SCALE).key, 'good');
    assert.equal(scale.levelOf(100, SCALE).key, 'slow');
    assert.equal(scale.levelOf(999, SCALE).key, 'slow');
    assert.equal(scale.levelOf(1000, SCALE).key, 'critical');
    assert.equal(scale.levelOf(null, SCALE), null);
    assert.equal(scale.levelOf('', SCALE), null);
    assert.equal(scale.levelOf('fast', SCALE), null);
    assert.equal(scale.levelOf(5, 'not a scale'), null);
});

test('record total: the count as written, with "+" when more records match than were counted', () => {
    const total = load('model/record-total');
    const grouped = (value) => String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    assert.equal(total.label(10000, true, grouped), '10,000+');
    assert.equal(total.label(184, false, grouped), '184');
    assert.equal(total.label('42', undefined, grouped), '42', 'only a true "at least" adds the sign');
    assert.equal(total.label('a lot', true, grouped), '0+');
    assert.equal(total.label(-3, false, grouped), '0');
});

test('change cell: the field is named, or several are counted', () => {
    const cell = load('model/change-cell');

    assert.deepEqual(plain(cell.field({change_field: 'Price', change_field_code: 'price', change_field_count: 1})),
        {count: 1, label: 'Price', code: 'price'});
    assert.deepEqual(plain(cell.field({change_field: 'Price, Name', change_field_code: null, change_field_count: '2'})),
        {count: 2, label: 'Price, Name', code: null});
    assert.deepEqual(plain(cell.field({change_field: '', change_field_count: 'none'})),
        {count: 0, label: null, code: null});
});

test('change cell: short values inline, long text by its word edits, JSON lists by items added and removed', () => {
    const cell = load('model/change-cell');

    assert.deepEqual(plain(cell.values({change_kind: 'short', change_before: '34.00', change_after: ''})), {
        kind: 'short', before: '34.00', after: null, edits: 0, added: 0, removed: 0, list: false
    });
    assert.equal(cell.values({change_kind: 'long', change_before: 'soft cotton shirt', change_after: 'soft linen shirt'})
        .edits, 2);
    assert.deepEqual(plain(cell.values({change_kind: 'json', change_before: '["a","b"]', change_after: '["b","c","d"]'})),
        {kind: 'json', before: '["a","b"]', after: '["b","c","d"]', edits: 0, added: 2, removed: 1, list: true});
    assert.equal(cell.values({change_kind: 'json', change_before: '{"a":1}', change_after: '[]'}).list, false);
    assert.equal(cell.values({change_kind: 'masked', change_before: '••••••', change_after: '••••••'}).kind, 'masked');
    assert.equal(cell.values({change_kind: 'script'}).kind, null, 'an unknown kind shows no value');
    assert.equal(cell.values({}).kind, null);
});

test('criterion event: a search key and a trimmed value of the length the search takes', () => {
    const event = load('search/criterion-event');

    assert.equal(event.NAME, 'activity-search:criterion');
    assert.deepEqual(plain(event.detail('by', '  Jane Doe ')), {key: 'by', value: 'Jane Doe', alone: false});
    assert.deepEqual(plain(event.detail('request', 'op1', true)), {key: 'request', value: 'op1', alone: true});
    assert.equal(event.detail('request', 'op1', 'yes').alone, false, 'only true asks for a filter alone');
    assert.equal(event.detail('By', 'jane'), null, 'keys are lower case');
    assert.equal(event.detail('request', '   '), null);
    assert.equal(event.detail('request', 42), null, 'values are text');
    assert.equal(event.detail('request', 'x'.repeat(event.MAX_VALUE + 1)), null);
    assert.deepEqual(plain(event.read({key: 'channel', value: 'entity', extra: true})),
        {key: 'channel', value: 'entity', alone: false});
    assert.deepEqual(plain(event.read({key: 'request', value: 'op1', alone: true})),
        {key: 'request', value: 'op1', alone: true});
    assert.equal(event.read(null), null);
    assert.equal(event.read('channel:entity'), null);
});

test('grid: a cell asks for the whole request alone through the bubbling criterion event; a bad value is not asked', () => {
    const announce = load('grid/announce-criterion');
    const sent = [];
    const cell = {dispatchEvent: (event) => sent.push(event)};

    assert.equal(announce({currentTarget: cell}, 'request', 'op1', true), true);
    assert.equal(announce({target: cell}, 'by', 'jane'), true);
    assert.equal(announce({currentTarget: cell}, 'request', '   ', true), false);
    assert.equal(announce(null, 'request', 'op1', true), false);
    assert.equal(sent.length, 2);
    assert.equal(sent[0].type, 'activity-search:criterion');
    assert.equal(sent[0].bubbles, true);
    assert.deepEqual(plain(sent[0].detail), {key: 'request', value: 'op1', alone: true});
    assert.deepEqual(plain(sent[1].detail), {key: 'by', value: 'jane', alone: false});

    const state = load('search/search-state').withCriterion(
        {chips: [{key: 'by', value: 'john'}], range: {preset: '7d', from: 1, to: 2}, changesOnly: true, view: 'grid'},
        load('search/criterion-event').read(sent[0].detail),
        {keys: ['by', 'request'], changeChannels: ['entity'], channels: {}}
    );
    assert.deepEqual(plain(state.state.chips), [{key: 'request', value: 'op1'}], 'the request replaces the chips');
    assert.equal(state.state.changesOnly, false);
});

test('change cell: a row\'s values are worked out once while its cell draws, and again only when they change', () => {
    const cell = load('model/change-cell');
    const row = {change_kind: 'long', change_before: 'soft cotton shirt', change_after: 'soft linen shirt'};
    const first = cell.values(row);

    assert.equal(cell.values(row), first, 'the same row gives the same values, without a second diff');
    assert.equal(first.edits, 2);
    row.change_after = 'hard linen shirt';
    assert.equal(cell.values(row).edits, 4, 'a row whose values changed is worked out anew');
});

test('selection actions: "Select All" is withheld while the count is only a lower bound', () => {
    const actions = load('model/selection-actions');

    assert.equal(actions.allows('selectAll', true), false);
    assert.equal(actions.allows('selectAll', false), true);
    assert.equal(actions.allows('selectAll', 'true'), true, 'only a true bound withholds it');
    assert.equal(actions.allows('selectPage', true), true, 'the page can still be selected');
    assert.equal(actions.allows('deselectAll', true), true);
});

test('latest request: only the request started last is still wanted', () => {
    const requests = load('model/latest-request').create();
    const first = requests.start();

    assert.equal(first(), true);
    const second = requests.start();
    assert.equal(first(), false, 'an answer to the earlier request comes too late');
    assert.equal(second(), true);
    assert.equal(load('model/latest-request').create().start()(), true, 'each grid keeps its own count');
});
