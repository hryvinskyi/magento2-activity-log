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

/**
 * @param {number} number
 * @param {string} text
 * @returns {Object} An unchanged side
 */
const same = (number, text) => ({number, op: 'equal', parts: [{text, changed: false}]});

/**
 * @param {number} before
 * @param {number} after
 * @param {string} text
 * @returns {Object} An unchanged row
 */
const equalRow = (before, after, text) => ({type: 'line', before: same(before, text), after: same(after, text)});

/**
 * @param {string} prefix
 * @param {number} from
 * @param {number} to
 * @returns {Array<string>} prefix + from … prefix + to
 */
const numbered = (prefix, from, to) => Array.from({length: to - from + 1}, (unused, index) => prefix + (from + index));

test('split diff: the bounds are three rows of context and 200 compared pairs', () => {
    const model = load('model/split-diff');

    assert.equal(model.CONTEXT, 3);
    assert.equal(model.MAX_HIGHLIGHTED_PAIRS, 200);
});

test('split diff: a commented-out list item is uncommented and its date changes', () => {
    const model = load('model/split-diff');
    const head = numbered('<li>Line ', 1, 20).map((line) => line + '</li>');
    const middle = numbered('<li>Term ', 1, 5).map((line) => line + '</li>');
    const tail = numbered('<li>Term ', 6, 10).map((line) => line + '</li>');
    const before = head.concat([
        '<!-- <li class="note">',
        '    Returns extended to 31/1/2026',
        '</li> -->'
    ], middle, tail).join('\n');
    const after = head.concat([
        '<li class="note">',
        '    Returns extended to 31/1/2027',
        '</li>'
    ], middle, ['<li>Gift wrapping</li>'], tail).join('\n');

    const result = plain(model.split(before, after));

    assert.equal(result.changes, 2);
    assert.equal(result.capped, false);
    assert.deepEqual(result.rows[0], {
        type: 'fold',
        rows: head.slice(0, 17).map((text, index) => equalRow(index + 1, index + 1, text))
    }, 'the lines further than three rows from the change are folded');
    assert.deepEqual(result.rows.slice(1, 4), [
        equalRow(18, 18, '<li>Line 18</li>'),
        equalRow(19, 19, '<li>Line 19</li>'),
        equalRow(20, 20, '<li>Line 20</li>')
    ]);
    assert.deepEqual(result.rows.slice(4, 7), [
        {
            type: 'line',
            before: {number: 21, op: 'delete', parts: [
                {text: '<!-- ', changed: true},
                {text: '<li class="note">', changed: false}
            ]},
            after: {number: 21, op: 'insert', parts: [{text: '<li class="note">', changed: false}]}
        },
        {
            type: 'line',
            before: {number: 22, op: 'delete', parts: [
                {text: '    Returns extended to 31/1/', changed: false},
                {text: '2026', changed: true}
            ]},
            after: {number: 22, op: 'insert', parts: [
                {text: '    Returns extended to 31/1/', changed: false},
                {text: '2027', changed: true}
            ]}
        },
        {
            type: 'line',
            before: {number: 23, op: 'delete', parts: [
                {text: '</li>', changed: false},
                {text: ' -->', changed: true}
            ]},
            after: {number: 23, op: 'insert', parts: [{text: '</li>', changed: false}]}
        }
    ], 'the k-th removed line sits beside the k-th added one, its changed words marked');
    assert.deepEqual(result.rows.slice(7, 12), middle.map((text, index) => equalRow(24 + index, 24 + index, text)),
        'five unchanged rows between two changes stay open: each is within three rows of one');
    assert.deepEqual(result.rows[12], {
        type: 'line',
        before: null,
        after: {number: 29, op: 'insert', parts: [{text: '<li>Gift wrapping</li>', changed: false}]}
    }, 'an added line with no partner sits beside nothing');
    assert.deepEqual(result.rows.slice(13, 16), [
        equalRow(29, 30, '<li>Term 6</li>'),
        equalRow(30, 31, '<li>Term 7</li>'),
        equalRow(31, 32, '<li>Term 8</li>')
    ], 'each side keeps its own numbering after a line the other side does not have');
    assert.deepEqual(result.rows[16], {
        type: 'fold',
        rows: [equalRow(32, 33, '<li>Term 9</li>'), equalRow(33, 34, '<li>Term 10</li>')]
    }, 'two hidden rows are folded');
    assert.equal(result.rows.length, 17);
});

test('split diff: one hidden row is left open, two are folded, at the start as at the end', () => {
    const model = load('model/split-diff');
    const open = plain(model.split(['x'].concat(numbered('e', 1, 4)).join('\n'), ['y'].concat(numbered('e', 1, 4)).join('\n')));
    const folded = plain(model.split(['x'].concat(numbered('e', 1, 5)).join('\n'), ['y'].concat(numbered('e', 1, 5)).join('\n')));
    const leading = plain(model.split(numbered('e', 1, 5).concat(['x']).join('\n'), numbered('e', 1, 5).concat(['y']).join('\n')));

    assert.equal(open.rows.length, 5, 'one change, three rows of context and the one row past them');
    assert.ok(open.rows.every((row) => row.type === 'line'));
    assert.equal(folded.rows.length, 5, 'one change, three rows of context and one fold');
    assert.deepEqual(folded.rows[4], {type: 'fold', rows: [equalRow(5, 5, 'e4'), equalRow(6, 6, 'e5')]});
    assert.deepEqual(leading.rows[0], {type: 'fold', rows: [equalRow(1, 1, 'e1'), equalRow(2, 2, 'e2')]});
    assert.equal(leading.rows.length, 5);
    assert.equal(leading.changes, 1);
});

test('split diff: equal texts are one fold, or their rows when there are fewer than two; empty texts are no rows', () => {
    const model = load('model/split-diff');
    const text = numbered('line ', 1, 40).join('\n');

    const equal = plain(model.split(text, text));

    assert.equal(equal.changes, 0);
    assert.equal(equal.rows.length, 1);
    assert.equal(equal.rows[0].type, 'fold');
    assert.equal(equal.rows[0].rows.length, 40);
    assert.deepEqual(equal.rows[0].rows[39], equalRow(40, 40, 'line 40'));

    assert.deepEqual(plain(model.split('only', 'only')), {rows: [equalRow(1, 1, 'only')], changes: 0, capped: false});
    assert.deepEqual(plain(model.split('', null)), {rows: [], changes: 0, capped: false});
});

test('split diff: lines that differ only in their line ending are unchanged', () => {
    const model = load('model/split-diff');

    assert.deepEqual(plain(model.split('a\r\nb', 'a\nb')), {
        rows: [{type: 'fold', rows: [equalRow(1, 1, 'a'), equalRow(2, 2, 'b')]}],
        changes: 0,
        capped: false
    }, 'nothing changed: the value is one fold');
    assert.deepEqual(plain(model.split('one\ntwo\nthree', 'one\r\ntwo words\r\nthree')), {
        rows: [
            equalRow(1, 1, 'one'),
            {
                type: 'line',
                before: {number: 2, op: 'delete', parts: [{text: 'two', changed: false}]},
                after: {number: 2, op: 'insert', parts: [{text: 'two', changed: false}, {text: ' words', changed: true}]}
            },
            equalRow(3, 3, 'three')
        ],
        changes: 1,
        capped: false
    }, 'a value saved again through a form, whose line breaks became "\\r\\n", shows only the line edited');
});

test('split diff: an added trailing space is the one part marked', () => {
    const model = load('model/split-diff');

    assert.deepEqual(plain(model.split('abc', 'abc ').rows), [{
        type: 'line',
        before: {number: 1, op: 'delete', parts: [{text: 'abc', changed: false}]},
        after: {number: 1, op: 'insert', parts: [{text: 'abc', changed: false}, {text: ' ', changed: true}]}
    }]);
});

test('split diff: lines that share less than 30 % of their characters are only marked as a whole', () => {
    const model = load('model/split-diff');

    assert.deepEqual(plain(model.split('Free delivery over 50', 'Gift wrapping on request').rows), [{
        type: 'line',
        before: {number: 1, op: 'delete', parts: [{text: 'Free delivery over 50', changed: false}]},
        after: {number: 1, op: 'insert', parts: [{text: 'Gift wrapping on request', changed: false}]}
    }], 'two different lines');
    assert.deepEqual(plain(model.split('  ', '\t').rows), [{
        type: 'line',
        before: {number: 1, op: 'delete', parts: [{text: '  ', changed: false}]},
        after: {number: 1, op: 'insert', parts: [{text: '\t', changed: false}]}
    }], 'lines with nothing but whitespace');

    const atThreshold = plain(model.split('abc 1234567', 'abc 7654321').rows[0]);

    assert.deepEqual(atThreshold.before.parts, [
        {text: 'abc ', changed: false},
        {text: '1234567', changed: true}
    ], '3 of 10 characters shared is 30 %: marked');

    const belowThreshold = plain(model.split('abc 12345678', 'abc 87654321').rows[0]);

    assert.deepEqual(belowThreshold.before.parts, [{text: 'abc 12345678', changed: false}],
        '3 of 11 characters shared is under 30 %: not marked');
});

test('split diff: only the first 200 pairs of a value are compared word by word', () => {
    const model = load('model/split-diff');
    const before = numbered('value ', 1, 250).map((line) => line + ' = old').join('\n');
    const after = numbered('value ', 1, 250).map((line) => line + ' = new').join('\n');

    const result = plain(model.split(before, after));

    assert.equal(result.rows.length, 250);
    assert.equal(result.changes, 1);
    assert.deepEqual(result.rows[199].after.parts, [
        {text: 'value 200 = ', changed: false},
        {text: 'new', changed: true}
    ]);
    assert.deepEqual(result.rows[200], {
        type: 'line',
        before: {number: 201, op: 'delete', parts: [{text: 'value 201 = old', changed: false}]},
        after: {number: 201, op: 'insert', parts: [{text: 'value 201 = new', changed: false}]}
    });
    assert.equal(result.rows.filter((row) => row.before.parts.some((part) => part.changed)).length, 200);
});

test('split diff: extra removed lines sit beside nothing, and the numbering of each side stays its own', () => {
    const model = load('model/split-diff');

    assert.deepEqual(plain(model.split('keep\nold line 1\nold 2\nold 3\nkeep too', 'keep\nnew line 1\nkeep too').rows), [
        equalRow(1, 1, 'keep'),
        {
            type: 'line',
            before: {number: 2, op: 'delete', parts: [{text: 'old', changed: true}, {text: ' line 1', changed: false}]},
            after: {number: 2, op: 'insert', parts: [{text: 'new', changed: true}, {text: ' line 1', changed: false}]}
        },
        {type: 'line', before: {number: 3, op: 'delete', parts: [{text: 'old 2', changed: false}]}, after: null},
        {type: 'line', before: {number: 4, op: 'delete', parts: [{text: 'old 3', changed: false}]}, after: null},
        equalRow(5, 3, 'keep too')
    ]);
});

test('split diff: a value too long to compare line by line is flagged', () => {
    const model = load('model/split-diff');
    const before = numbered('a', 1, 1001).join('\n');
    const after = numbered('b', 1, 1001).join('\n');

    const result = model.split(before, after);

    assert.equal(result.capped, true);
    assert.equal(result.rows.length, 1001);
    assert.equal(result.changes, 1);
});
