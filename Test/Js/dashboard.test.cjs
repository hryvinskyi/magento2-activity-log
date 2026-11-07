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

const WORDING = {label: 'Label', total: 'Total'};

test('channel selection: toggles in chip order and never switches the last one off', () => {
    const selection = load('dashboard/channel-selection');
    const order = ['zz_one', 'zz_two', 'zz_change', 'zz_request'];

    assert.deepEqual(
        plain(selection.toggle(order, ['zz_request', 'zz_one'], 'zz_two')),
        ['zz_one', 'zz_two', 'zz_request']
    );
    assert.deepEqual(plain(selection.toggle(order, ['zz_one', 'zz_request'], 'zz_one')), ['zz_request']);
    assert.deepEqual(plain(selection.toggle(order, ['zz_request'], 'zz_request')), ['zz_request']);
});

test('chart table: one row per label, a column per series, a total when stacked', () => {
    const chartTable = load('dashboard/chart-table');

    const table = chartTable.toTable({
        labels: ['Oct 14', 'Oct 15'],
        datasets: [{label: 'Series one', values: [3, 4]}, {label: 'Series two', values: [1, 0]}],
        stacked: true
    }, WORDING);

    assert.deepEqual(
        plain(table.columns.map((column) => column.label)),
        ['Label', 'Series one', 'Series two', 'Total']
    );
    assert.equal(table.rows.length, 2);
    assert.equal(table.rows[0].cells.label, 'Oct 14');
    assert.equal(table.rows[0].cells.total, (4).toLocaleString());
});

test('chart config: horizontal bars put labels on the y axis and keep the unit in ticks', () => {
    const chartConfig = load('dashboard/chart-config');

    const config = chartConfig.build({
        labels: ['POST /V1/acme-shop/widgets'],
        datasets: [{label: 'Average duration', color: ['#C8372D'], values: [1380]}],
        horizontal: true,
        unit: 'ms'
    }, 'bar', WORDING);

    assert.equal(config.type, 'bar');
    assert.equal(config.options.indexAxis, 'y');
    assert.equal(config.options.plugins.legend.display, false);
    assert.equal(config.options.scales.x.ticks.callback(1380), (1380).toLocaleString() + ' ms');
});

test('response parser: accepts the server shape and refuses anything else', () => {
    const parser = load('dashboard/response-parser');
    const good = {
        periodDays: 7,
        channels: ['zz_one'],
        summary: [{
            key: 'total', label: 'Total', value: '3', unit: '', delta: '+1%', direction: 'up', tone: 'good', empty: false
        }],
        widgets: {volume: {status: 'ok', data: {labels: [], datasets: []}}, broken: {status: 'error'}}
    };

    assert.equal(parser.parseData(good).periodDays, 7);
    assert.throws(() => parser.parseData({...good, channels: [1]}), {name: 'DashboardShapeError'});
    assert.throws(() => parser.parseData({...good, widgets: {volume: {status: 'ok'}}}), {name: 'DashboardShapeError'});
    assert.throws(() => parser.parseData(null), {name: 'DashboardShapeError'});
});
