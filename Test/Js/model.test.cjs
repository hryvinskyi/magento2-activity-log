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

test('url filters: only allowed, non-empty, reasonably short values', () => {
    const urlFilters = load('model/url-filters');

    assert.deepEqual(plain(urlFilters.read('?correlation_id=a1f3c9e7&channel=&evil=1&actor_name=%20jane.doe%20', [
            'correlation_id',
            'channel',
            'actor_name'
        ])),
        {correlation_id: 'a1f3c9e7', actor_name: 'jane.doe'}
    );
    assert.deepEqual(plain(urlFilters.read('?correlation_id=' + 'x'.repeat(300), ['correlation_id'])), {});
    assert.deepEqual(plain(urlFilters.read('', ['correlation_id'])), {});
});
