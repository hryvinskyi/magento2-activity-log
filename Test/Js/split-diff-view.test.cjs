/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const {load} = require('./amd.cjs');
const {FakeDocument, builder} = require('./dom-fake.cjs');

const WORDS = {fold: 'Show %1 unchanged lines', filler: 'No line'};

/**
 * @param {string} prefix
 * @param {number} from
 * @param {number} to
 * @returns {Array<string>} prefix + from … prefix + to
 */
const numbered = (prefix, from, to) => Array.from({length: to - from + 1}, (unused, index) => prefix + (from + index));

/**
 * Fifteen unchanged lines, a price that changed, a note that was removed, three unchanged lines
 *
 * @returns {{before: string, after: string}}
 */
function priceChange() {
    const head = numbered('line ', 1, 15);
    const tail = numbered('end ', 1, 3);

    return {
        before: head.concat(['Price: 100 kr', 'Old note'], tail).join('\n'),
        after: head.concat(['Price: 120 kr'], tail).join('\n')
    };
}

/**
 * @param {string} before
 * @param {string} after
 * @param {Object=} options
 * @returns {{doc: FakeDocument, table: Object}} The table drawn from the real split model, in the page
 */
function draw(before, after, options) {
    const doc = new FakeDocument();
    const table = load('view/split-diff').render(doc, load('model/split-diff').split(before, after), WORDS, options);

    doc.body.appendChild(table);

    return {doc, table};
}

/**
 * @param {Object} row
 * @returns {boolean} Whether a person sees the row
 */
const isShown = (row) => !row.hidden && !row.parentNode.hidden;

/**
 * @param {Object} row a split-diff row
 * @returns {Array<string>} The four cells as a person reads them: number, text, number, text
 */
const cells = (row) => row.children.map((cell) => cell.textContent);

test('split diff view: a table of four columns, the folded rows hidden behind a labelled button', () => {
    const counted = [];
    const {table} = draw(priceChange().before, priceChange().after, {
        formatNumber: (count) => {
            counted.push(count);

            return 'twelve';
        }
    });
    const rows = table.querySelectorAll('tr');
    const shown = rows.filter(isShown);
    const fold = table.querySelector('tr.split-diff__fold');
    const button = fold.querySelector('button');

    assert.equal(table.tagName, 'TABLE');
    assert.ok(table.classList.contains('split-diff'));
    assert.deepEqual(table.querySelector('colgroup').children.map((col) => col.className), [
        'split-diff__col-num', 'split-diff__col-code', 'split-diff__col-num', 'split-diff__col-code'
    ]);
    assert.equal(rows.length, 1 + 12 + 8, 'the fold row, the twelve rows it hides and the eight shown');
    assert.equal(shown.length, 9);
    assert.deepEqual(counted, [12], 'the count goes through the number format');
    assert.equal(button.textContent, 'Show twelve unchanged lines');
    assert.equal(button.getAttribute('type'), 'button');
    assert.equal(button.getAttribute('data-action'), 'fold');
    assert.equal(fold.children.length, 1);
    assert.equal(fold.children[0].getAttribute('colspan'), '4');
    assert.ok(fold.parentNode.classList.contains('split-diff__fold-group'));
    assert.equal(fold.parentNode.children[0], fold, 'the fold row leads its own group');
    assert.deepEqual(fold.parentNode.children.slice(1).map((row) => row.hidden), new Array(12).fill(true));
    assert.notEqual(fold.parentNode, shown[1].parentNode, 'rows that are not folded are in a body of their own');

    assert.deepEqual(shown.slice(1).map(cells), [
        ['13', 'line 13', '13', 'line 13'],
        ['14', 'line 14', '14', 'line 14'],
        ['15', 'line 15', '15', 'line 15'],
        ['16', 'Price: 100 kr', '16', 'Price: 120 kr'],
        ['17', 'Old note', '', 'No line'],
        ['18', 'end 1', '17', 'end 1'],
        ['19', 'end 2', '18', 'end 2'],
        ['20', 'end 3', '19', 'end 3']
    ], 'each side numbers its own lines');
    assert.deepEqual(shown.slice(1).map((row) => row.classList.contains('split-diff__row--change')),
        [false, false, false, true, true, false, false, false]);
});

test('split diff view: changed words are marked in a pair, a line with no partner is marked whole', () => {
    const {table} = draw(priceChange().before, priceChange().after);
    const [pair, single] = table.querySelectorAll('tr.split-diff__row').filter((row) => row.classList.contains(
        'split-diff__row--change'));

    assert.deepEqual(pair.querySelectorAll('del.split-diff__word').map((node) => node.textContent), ['100']);
    assert.deepEqual(pair.querySelectorAll('ins.split-diff__word').map((node) => node.textContent), ['120']);
    assert.deepEqual(pair.children[1].children.map((node) => [node.tagName, node.textContent]),
        [['SPAN', 'Price: '], ['DEL', '100'], ['SPAN', ' kr']]);
    assert.deepEqual(pair.children.map((cell) => cell.className), [
        'split-diff__num split-diff__num--delete', 'split-diff__code split-diff__code--delete',
        'split-diff__num split-diff__num--insert', 'split-diff__code split-diff__code--insert'
    ]);

    assert.deepEqual(single.querySelectorAll('del').map((node) => [node.className, node.textContent]),
        [['split-diff__line', 'Old note']], 'the whole removed line, so the change is not told by colour alone');
    assert.deepEqual(single.children.map((cell) => cell.className), [
        'split-diff__num split-diff__num--delete', 'split-diff__code split-diff__code--delete',
        'split-diff__num split-diff__num--empty', 'split-diff__code split-diff__code--empty'
    ]);
    assert.deepEqual(single.children[3].children.map((node) => [node.tagName, node.className, node.textContent]),
        [['SPAN', 'split-diff__sr', 'No line']], 'the empty side is named in visually hidden text');
    assert.equal(single.children[3].getAttribute('aria-label'), null);
});

test('split diff view: an added line sits beside a named empty side', () => {
    const {table} = draw('a\nb', 'a\nnew\nb');
    const added = table.querySelector('tr.split-diff__row--change');

    assert.deepEqual(cells(added), ['', 'No line', '2', 'new']);
    assert.deepEqual(added.querySelectorAll('ins').map((node) => [node.className, node.textContent]),
        [['split-diff__line', 'new']]);
});

test('split diff view: lines that changed only their line ending are drawn unchanged', () => {
    const before = numbered('line ', 1, 6).join('\n') + '\nPrice: 100 kr';
    const after = numbered('line ', 1, 6).join('\r\n') + '\r\nPrice: 120 kr';
    const {table} = draw(before, after);
    const changed = table.querySelectorAll('tr').filter((row) => row.classList.contains('split-diff__row--change'));

    assert.equal(changed.length, 1, 'only the line whose text changed');
    assert.deepEqual(cells(changed[0]), ['7', 'Price: 100 kr', '7', 'Price: 120 kr']);
    assert.equal(table.querySelector('tr.split-diff__fold').textContent, 'Show 3 unchanged lines',
        'the lines whose ending alone changed fold away with the rest of the unchanged text');
});

test('split diff view: opening a fold shows its rows, hides its own row and moves the focus to the first', () => {
    const {doc, table} = draw(priceChange().before, priceChange().after);
    const view = load('view/split-diff');
    const fold = table.querySelector('tr.split-diff__fold');
    const button = fold.querySelector('button');
    const group = fold.parentNode;

    button.focus();

    assert.equal(view.expand(button), true);
    assert.equal(fold.hidden, true);
    assert.ok(table.contains(button), 'the fold row stays in the table, so the click still landed inside it');
    assert.deepEqual(group.children.slice(1).map((row) => row.hidden), new Array(12).fill(false));
    assert.deepEqual(cells(group.children[1]), ['1', 'line 1', '1', 'line 1']);
    assert.equal(doc.activeElement, group.children[1]);
    assert.equal(group.children[1].getAttribute('tabindex'), '-1');
    assert.equal(group.children[2].hasAttribute('tabindex'), false, 'only the row that takes the focus');
    assert.equal(view.expand(button), false, 'an open fold stays open');
});

test('split diff view: anything but a fold button is not expanded', () => {
    const {table} = draw(priceChange().before, priceChange().after);
    const view = load('view/split-diff');
    const el = builder(new FakeDocument());
    const stray = el('tbody', {className: 'split-diff__fold-group'}, [
        el('tr', {className: 'split-diff__fold'}, [el('td', {}, [el('button', {'data-action': 'other'})])])
    ]);
    const outside = el('div', {}, [el('button', {'data-action': 'fold'})]);

    assert.equal(view.expand(table.querySelector('td')), false);
    assert.equal(view.expand(stray.querySelector('button')), false);
    assert.equal(view.expand(outside.querySelector('button')), false);
    assert.equal(view.expand(null), false);
    assert.equal(table.querySelector('tr.split-diff__fold').hidden, false);
});

test('split diff view: wording that is not text is refused rather than drawn as "undefined"', () => {
    const view = load('view/split-diff');
    const model = load('model/split-diff').split('a', 'b');
    const doc = new FakeDocument();

    assert.throws(() => view.render(doc, model, {fold: 'Show %1 unchanged lines'}), {name: 'TypeError'});
    assert.throws(() => view.render(doc, model, {filler: 'No line', fold: 3}), {name: 'TypeError'});
    assert.throws(() => view.render(doc, model, null), {name: 'TypeError'});
    assert.throws(() => view.render(doc, model, WORDS, {formatNumber: 'en_GB'}), {name: 'TypeError'});
    assert.throws(() => view.render(doc, {}, WORDS), {name: 'TypeError'});
});

test('split diff view: markup in a value is shown as text, never built', () => {
    const {table} = draw('<script>alert(1)</script>', '<b onclick="x()">bold</b>');
    const tags = new Set(table.descendants().map((node) => node.tagName));

    assert.ok(!tags.has('SCRIPT'));
    assert.ok(!tags.has('B'));
    assert.deepEqual(cells(table.querySelector('tr.split-diff__row--change')),
        ['1', '<script>alert(1)</script>', '1', '<b onclick="x()">bold</b>']);
});

test('split diff view: equal texts are one closed fold, no texts are no rows', () => {
    const lines = numbered('line ', 1, 5).join('\n');
    const {table} = draw(lines, lines);

    assert.equal(table.querySelectorAll('tr').filter(isShown).length, 1);
    assert.equal(table.querySelector('button').textContent, 'Show 5 unchanged lines', 'String is the default format');
    assert.equal(draw('', '').table.querySelectorAll('tr').length, 0);
});

test('dom fake: attributes and classes answer as a browser does', () => {
    const el = builder(new FakeDocument());
    const node = el('div', {'data-expand-label': 'More', tabindex: '-1'});

    assert.equal(node.hasAttribute('data-expand-label'), true);
    assert.equal(node.hasAttribute('tabindex'), true);
    assert.equal(node.hasAttribute('title'), false);
    node.removeAttribute('data-expand-label');
    assert.equal(node.hasAttribute('data-expand-label'), false);
    assert.equal(node.getAttribute('data-expand-label'), null);

    assert.equal(node.classList.toggle('is-open'), true);
    assert.equal(node.classList.contains('is-open'), true);
    assert.equal(node.classList.toggle('is-open'), false);
    assert.equal(node.classList.contains('is-open'), false);
    assert.equal(node.classList.toggle('is-open', true), true);
    assert.equal(node.classList.toggle('is-open', true), true);
    assert.equal(node.classList.toggle('is-open', false), false);
});
