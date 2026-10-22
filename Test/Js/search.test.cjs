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

const KEYS = ['by', 'via', 'channel', 'request', 'store', 'record', 'setting', 'property', 'content'];

test('grammar: "key: value" names a known key, anything else is text', () => {
    const grammar = load('search/grammar');

    assert.deepEqual(plain(grammar.parse('by: john', KEYS)), {ok: true, chip: {key: 'by', value: 'john'}, error: null});
    assert.deepEqual(plain(grammar.parse('  Record :  SKU-1 ', KEYS).chip), {key: 'record', value: 'SKU-1'});
    assert.deepEqual(plain(grammar.parse('SKU-1', KEYS).chip), {key: 'text', value: 'SKU-1'});
    assert.deepEqual(plain(grammar.parse('colour: red', KEYS).chip), {key: 'text', value: 'colour: red'},
        'an unknown key is searched as text, colon included');
    assert.deepEqual(plain(grammar.parse('https://example.com/a', KEYS).chip),
        {key: 'text', value: 'https://example.com/a'});
    assert.deepEqual(plain(grammar.parse('setting: section/group/flag: x', KEYS).chip),
        {key: 'setting', value: 'section/group/flag: x'}, 'split at the first colon only');
    assert.deepEqual(plain(grammar.parse('text: by: john', KEYS).chip), {key: 'text', value: 'by: john'});
});

test('grammar: a key with no value is text; empty and too long input are refused as values', () => {
    const grammar = load('search/grammar');

    assert.deepEqual(plain(grammar.parse('   ', KEYS)), {ok: false, chip: null, error: 'empty'});
    assert.deepEqual(plain(grammar.parse('by:', KEYS)), {ok: true, chip: {key: 'text', value: 'by:'}, error: null});
    assert.deepEqual(plain(grammar.parse('  by:    ', KEYS).chip), {key: 'text', value: 'by:'});
    assert.deepEqual(plain(grammar.parse('text:', KEYS).chip), {key: 'text', value: 'text:'});
    assert.equal(grammar.parse('nokey:', KEYS).chip.value, 'nokey:', 'an unknown key with no value is text');
    assert.equal(grammar.parse('by: ' + 'x'.repeat(256), KEYS).error, 'tooLong');
    assert.equal(grammar.parse('by: ' + 'x'.repeat(255), KEYS).ok, true);
    assert.equal(grammar.parse('😀'.repeat(255), KEYS).ok, true, 'length counts characters, not UTF-16 units');
});

test('grammar: no duplicates, at most 20 chips, removal by index and from the end', () => {
    const grammar = load('search/grammar');
    let chips = [{key: 'by', value: 'John'}];

    const again = grammar.add(chips, {key: 'by', value: 'john'});
    assert.equal(again.ok, true);
    assert.equal(again.added, false);
    assert.equal(again.chips.length, 1);

    const other = grammar.add(chips, {key: 'via', value: 'john'});
    assert.equal(other.added, true);
    assert.equal(other.chips.length, 2);
    assert.equal(chips.length, 1, 'the input list is not changed');

    for (let index = 0; chips.length < grammar.MAX_CHIPS; index += 1) {
        chips = grammar.add(chips, {key: 'text', value: 'word' + index}).chips;
    }
    assert.equal(chips.length, 20);
    assert.deepEqual(plain(grammar.add(chips, {key: 'text', value: 'one more'})).error, 'limit');
    assert.equal(grammar.add(chips, {key: 'by', value: 'JOHN'}).ok, true, 'a duplicate is no error at the limit');

    assert.deepEqual(plain(grammar.remove([{key: 'a', value: '1'}, {key: 'b', value: '2'}], 0)), [{key: 'b', value: '2'}]);
    assert.deepEqual(plain(grammar.remove([{key: 'a', value: '1'}], 5)), [{key: 'a', value: '1'}]);
    assert.deepEqual(plain(grammar.removeLast([{key: 'a', value: '1'}, {key: 'b', value: '2'}])), [{key: 'a', value: '1'}]);
    assert.deepEqual(plain(grammar.removeLast([])), []);
});

test('grammar: links carry chips and range, older links still work, bad values are counted', () => {
    const grammar = load('search/grammar');

    assert.deepEqual(plain(grammar.fromUrl('?setting=section/group/enabled&range=30d', KEYS)), {
        chips: [{key: 'setting', value: 'section/group/enabled'}], rangeToken: '30d', dropped: 0
    });
    assert.deepEqual(plain(grammar.fromUrl('?channel=admin', KEYS).chips), [{key: 'channel', value: 'admin'}]);
    assert.deepEqual(plain(grammar.fromUrl('correlation_id=c7a1e9&actor_name=john&isAjax=true', KEYS)), {
        chips: [{key: 'request', value: 'c7a1e9'}, {key: 'by', value: 'john'}], rangeToken: null, dropped: 0
    });
    assert.deepEqual(plain(grammar.fromUrl('?record=&by=a&by=A&text=x', KEYS)), {
        chips: [{key: 'by', value: 'a'}, {key: 'text', value: 'x'}], rangeToken: null, dropped: 2
    });

    const many = Array.from({length: 25}, (unused, index) => 'text=w' + index).join('&');
    const capped = grammar.fromUrl(many, KEYS);
    assert.equal(capped.chips.length, 20);
    assert.equal(capped.dropped, 5);
    assert.equal(grammar.fromUrl('?by=' + 'x'.repeat(256), KEYS).dropped, 1);
});

test('grammar: a link round-trips chips with any characters', () => {
    const grammar = load('search/grammar');
    const chips = [
        {key: 'text', value: 'a & b = c?'},
        {key: 'setting', value: 'section/group/url'},
        {key: 'by', value: 'Jørgen Ødegård'},
        {key: 'record', value: '#1245'}
    ];

    const query = grammar.toUrl(chips, '1729000000000-1729086400000');
    const back = grammar.fromUrl('?' + query, KEYS);

    assert.deepEqual(plain(back.chips), chips);
    assert.equal(back.rangeToken, '1729000000000-1729086400000');
    assert.equal(grammar.toUrl([], null), '');
});

const describe = (key, label, hint, group, matchesFieldNames = false) => ({key, label, hint, group, matchesFieldNames});
const VOCABULARY = {
    keys: [
        describe('by', 'Actor', 'name or kind', 'people'),
        describe('via', 'Client', 'client', 'people'),
        describe('text', 'Text', 'anywhere', 'other'),
        describe('record', 'Record', 'code or id', 'records'),
        describe('kind', 'Kind', 'kind of record', 'types'),
        describe('property', 'Property', 'code or label', 'fields', true),
        describe('content', 'Content', 'before or after', 'other'),
        describe('place', 'Place', 'where', 'other')
    ],
    lists: {
        by: [
            {value: 'john', label: 'john', hint: 'Admin', count: 12},
            {value: 'joanna', label: 'joanna', hint: 'Admin', count: 3},
            {value: 'cron', label: 'cron', hint: 'Cron', count: 40}
        ],
        via: [{value: 'Jolly Client', label: 'Jolly Client', hint: '', count: 2}],
        record: [
            {value: 'SKU-1', label: 'Jogging Bag', hint: 'Record', count: 4},
            {value: 'SKU-2', label: 'Joker Mug', hint: 'Record', count: 1}
        ],
        kind: Array.from({length: 6}, (unused, index) => ({value: 'job' + index, label: 'Job ' + index, hint: '', count: 1})),
        property: Array.from({length: 12}, (unused, index) => ({value: 'price' + index, label: 'Price ' + index, hint: '', count: null})),
        place: [{value: 'Jordan Store', label: 'Jordan Store', hint: '', count: 5}]
    }
};
const shapeOf = (groups) => groups.map((group) => group.type + ':' + (group.group || group.key));

test('suggest: nothing typed lists every key but text', () => {
    const suggest = load('search/suggest');
    const groups = plain(suggest.suggest('', [], VOCABULARY));

    assert.equal(groups.length, 1);
    assert.equal(groups[0].type, 'keys');
    assert.deepEqual(groups[0].items.map((item) => item.key), ['by', 'via', 'record', 'kind', 'property', 'content', 'place']);
    assert.equal(groups[0].items[0].kind, 'key');
});

test('suggest: plain words are offered in the design\'s groups, in their order, then as text', () => {
    const suggest = load('search/suggest');
    const groups = plain(suggest.suggest('jo', [{key: 'by', value: 'JOHN'}], VOCABULARY));

    assert.deepEqual(shapeOf(groups), ['group:records', 'group:people', 'group:types', 'text:text'],
        'values of the "other" group wait for their key to be typed');
    assert.deepEqual(groups[0].items.map((item) => item.key + ':' + item.value), ['record:SKU-1', 'record:SKU-2'],
        'the label matches too');
    assert.deepEqual(groups[1].items.map((item) => item.key + ':' + item.value), ['by:joanna', 'via:Jolly Client'],
        'one group joins the values of several keys, and leaves out values already used as chips');
    assert.equal(groups[2].items.length, suggest.LIMITS.types);
    assert.deepEqual(groups[3].items[0], {kind: 'text', key: 'text', value: 'jo', label: 'jo', hint: 'anywhere', count: null});

    const keyed = plain(suggest.suggest('Pr', [], VOCABULARY));
    assert.deepEqual(shapeOf(keyed), ['keys:null', 'group:fields', 'text:text']);
    assert.deepEqual(keyed[0].items.map((item) => item.key), ['property']);
    assert.equal(keyed[1].items.length, suggest.LIMITS.fields);

    const byLabel = plain(suggest.suggest('acto', [], VOCABULARY));
    assert.deepEqual(byLabel[0].items.map((item) => item.key), ['by'], 'a key matches by its label');
    assert.deepEqual(plain(suggest.WORD_GROUPS), ['fields', 'records', 'people', 'types']);
});

test('suggest: after "key:" its values and the value as typed; an unknown key is text', () => {
    const suggest = load('search/suggest');

    const all = plain(suggest.suggest('property:', [], VOCABULARY));
    assert.deepEqual(shapeOf(all), ['matching:property']);
    assert.equal(all[0].items.length, suggest.LIMITS.matching);

    const typed = plain(suggest.suggest('by: cr', [], VOCABULARY));
    assert.deepEqual(shapeOf(typed), ['matching:by', 'typed:by']);
    assert.deepEqual(typed[0].items.map((item) => item.value), ['cron']);
    assert.deepEqual(typed[1].items[0], {kind: 'typed', key: 'by', value: 'cr', label: 'cr', hint: 'name or kind', count: null});

    assert.deepEqual(plain(suggest.suggest('place:', [], VOCABULARY))[0].items.map((item) => item.value), ['Jordan Store'],
        'a key of the "other" group offers its values once typed');
    assert.deepEqual(plain(suggest.suggest('content:', [], VOCABULARY)), [], 'a key without a list offers nothing yet');
    assert.deepEqual(shapeOf(plain(suggest.suggest('content: red', [], VOCABULARY))), ['typed:content']);

    const unknown = plain(suggest.suggest('colour: red', [], VOCABULARY));
    assert.equal(unknown.length, 1);
    assert.deepEqual(unknown[0].items[0], {
        kind: 'text', key: 'text', value: 'colour: red', label: 'colour: red', hint: 'unknownKey', count: null
    });
});

test('suggest: flattening for the arrow keys, and choosing an item', () => {
    const suggest = load('search/suggest');
    const groups = suggest.suggest('jo', [], VOCABULARY);
    const keys = VOCABULARY.keys.map((description) => description.key);

    assert.deepEqual(plain(suggest.flatten(groups).map((item) => item.kind)),
        ['value', 'value', 'value', 'value', 'value', 'value', 'value', 'value', 'value', 'text']);
    assert.deepEqual(plain(suggest.choose({kind: 'key', key: 'by', value: ''}, keys)),
        {type: 'continue', input: 'by: ', result: null});
    assert.deepEqual(plain(suggest.choose({kind: 'value', key: 'by', value: 'john'}, keys).result.chip),
        {key: 'by', value: 'john'});
    assert.deepEqual(plain(suggest.choose({kind: 'text', key: 'text', value: 'colour: red'}, keys).result.chip),
        {key: 'text', value: 'colour: red'});
    assert.equal(suggest.choose({kind: 'typed', key: 'by', value: 'x'.repeat(300)}, keys).result.error, 'tooLong');
});

test('request: the query string carries range, chips, changes only, cursor and limit', () => {
    const request = load('search/request');
    const query = request.toQuery({
        range: {from: 1000, to: 2000},
        chips: [{key: 'by', value: 'john'}, {key: 'setting', value: 'a/b:c'}],
        changesOnly: true,
        cursor: 'abc~-_',
        limit: 50
    });
    const params = new URLSearchParams(query);

    assert.equal(params.get('from'), '1000');
    assert.equal(params.get('to'), '2000');
    assert.deepEqual(params.getAll('c[]'), ['by:john', 'setting:a/b:c']);
    assert.equal(params.get('changes'), '1');
    assert.equal(params.get('cursor'), 'abc~-_');
    assert.equal(params.get('limit'), '50');

    const first = new URLSearchParams(request.toQuery({range: {from: 0, to: 0}, chips: [], changesOnly: false, cursor: null}));
    assert.equal(first.get('changes'), '0');
    assert.equal(first.has('cursor'), false);
    assert.equal(first.has('limit'), false);

    assert.throws(() => request.toQuery({range: {from: 2, to: 1}, chips: [], changesOnly: false}), {name: 'RangeError'});
    assert.throws(() => request.toQuery({range: {from: -1, to: 1}, chips: [], changesOnly: false}), {name: 'RangeError'});
    assert.throws(() => request.toQuery({
        range: {from: 0, to: 1}, chips: Array.from({length: 21}, () => ({key: 'text', value: 'x'})), changesOnly: false
    }), {name: 'RangeError'});
    assert.throws(() => request.toQuery({range: {from: 0, to: 1}, chips: [], changesOnly: false, limit: 201}),
        {name: 'RangeError'});
});

test('request: endpoint addresses keep their own parameters; siblings span the whole log', () => {
    const request = load('search/request');

    assert.equal(request.withQuery('/admin/activitylog/activity/search/key/abc/', 'from=1'),
        '/admin/activitylog/activity/search/key/abc/?from=1');
    assert.equal(request.withQuery('/search?x=1', 'from=1'), '/search?x=1&from=1');
    assert.equal(request.withQuery('/search', ''), '/search');

    assert.deepEqual(plain(request.siblingsOf('c7a1e9', 5000)), {
        range: {from: 0, to: 5000}, chips: [{key: 'request', value: 'c7a1e9'}], changesOnly: false, cursor: null, limit: 200
    });
});

/**
 * A result page in the shape the search endpoint sends
 */
function pageBody() {
    return {
        events: [{
            id: 220901,
            occurredAtMs: 1792671912410,
            channel: 'records',
            correlationId: 'c7a1e9',
            siblingCount: 1,
            actor: {type: 'admin', name: 'john', viaClient: null, viaVersion: null, viaChannel: null},
            action: 'Record saved',
            outcome: 'success',
            message: null,
            durationMs: null,
            ip: '10.0.4.21',
            subject: {type: 'record', id: '1245', label: 'Record SKU-1 · Jogging Bag'},
            verb: 'changed',
            detail: {
                what: 'Price',
                fieldCount: 1,
                automaticCount: 0,
                subjectTypeLabel: 'Record',
                subjectLabel: 'SKU-1 · Jogging Bag',
                subjectUrl: 'https://shop.test/admin/record/edit/id/1245/',
                fields: [{
                    name: 'price', label: 'Price', scope: 'Default Store View', before: '34.00', after: '39.00',
                    kind: 'short', changed: true, automatic: false, truncated: false, beforeLength: 5,
                    afterLength: 5
                }],
                moreFieldCount: 0,
                unchangedCount: 0,
                unchangedRecorded: true
            },
            detailUrl: '/admin/activitylog/activity/view/id/220901/'
        }, {
            id: 220900,
            occurredAtMs: 1792671912380,
            channel: 'admin',
            correlationId: 'c7a1e9',
            siblingCount: 1,
            actor: {type: 'admin', name: 'john', viaClient: null, viaVersion: null, viaChannel: null},
            action: 'section/item/save',
            outcome: 'success',
            message: null,
            durationMs: 412.5,
            ip: null,
            subject: null,
            verb: 'performed',
            detail: null,
            detailUrl: '/admin/activitylog/activity/view/id/220900/'
        }],
        nextCursor: 'MTc5MjY3MTkxMjM4MC4yMjA5MDAuYWJj',
        narrowed: false,
        total: {count: 2, atLeast: false},
        outside: null,
        latestOutsideMs: null,
        overall: {count: 10000, atLeast: true},
        journalEmpty: false
    };
}

test('request: a result page in the server shape is copied into the page shape', () => {
    const request = load('search/request');
    const page = request.parsePage(pageBody());

    assert.equal(page.events.length, 2);
    assert.equal(page.events[0].detail.fields[0].kind, 'short');
    assert.equal(page.events[0].detail.fields[0].automatic, false);
    assert.equal(page.events[0].detail.automaticCount, 0);
    assert.equal(page.events[1].detail, null);
    assert.equal(page.events[1].durationMs, 412.5);
    assert.equal(page.nextCursor, 'MTc5MjY3MTkxMjM4MC4yMjA5MDAuYWJj');
    assert.deepEqual(plain(page.overall), {count: 10000, atLeast: true});
    assert.equal(page.outside, null);

    const unknownField = pageBody();
    unknownField.events[0].extra = '<script>';
    assert.equal(Object.prototype.hasOwnProperty.call(request.parsePage(unknownField).events[0], 'extra'), false,
        'only the known fields are copied');
});

test('request: a result page of any other shape is refused', () => {
    const request = load('search/request');
    const broken = [
        (body) => { delete body.events[0].detail.fields[0].kind; },
        (body) => { body.events[0].detail.fields[0].kind = 'html'; },
        (body) => { delete body.events[0].detail.fields[0].automatic; },
        (body) => { body.events[0].detail.fields[0].automatic = 'yes'; },
        (body) => { body.events[0].detail.automaticCount = -1; },
        (body) => { body.events[0].verb = 'exploded'; },
        (body) => { body.events[0].outcome = 'maybe'; },
        (body) => { body.events[0].detail.subjectUrl = 'javascript:alert(1)'; },
        (body) => { body.events[0].detailUrl = 'data:text/html,x'; },
        (body) => { body.events[0].siblingCount = -1; },
        (body) => { body.events[0].id = '220901'; },
        (body) => { body.events[0].durationMs = 'slow'; },
        (body) => { body.events[0].actor = null; },
        (body) => { delete body.events[0].ip; },
        (body) => { body.nextCursor = ''; },
        (body) => { body.total = {count: 1}; },
        (body) => { body.journalEmpty = 'no'; },
        (body) => { body.events = {}; }
    ];

    broken.forEach((breakIt, index) => {
        const body = pageBody();
        breakIt(body);
        assert.throws(() => request.parsePage(body), {name: 'SearchShapeError'}, 'case ' + index);
    });
    assert.throws(() => request.parsePage(null), {name: 'SearchShapeError'});
    assert.throws(() => request.parsePage([]), {name: 'SearchShapeError'});
});

test('request: suggestion lists and refusals', () => {
    const request = load('search/request');

    const parsed = request.parseSuggestions({
        keys: [{key: 'by', label: 'Actor', hint: 'name', group: 'people', matchesFieldNames: false}],
        lists: {by: [{value: 'john', label: 'john', hint: 'Admin', count: 3}, {value: 'x', label: 'x', hint: '', count: null}]}
    });
    assert.deepEqual(plain(parsed.lists.by[1]), {value: 'x', label: 'x', hint: '', count: null});
    assert.deepEqual(plain(parsed.keys[0]), {key: 'by', label: 'Actor', hint: 'name', group: 'people', matchesFieldNames: false});
    assert.deepEqual(plain(request.parseSuggestions({keys: [], lists: []})), {keys: [], lists: {}},
        'an empty map arrives from PHP as an empty list');

    const key = {key: 'by', label: 'x', hint: '', group: 'other', matchesFieldNames: false};
    assert.throws(() => request.parseSuggestions({keys: [{...key, key: 'By'}], lists: {}}), {name: 'SearchShapeError'});
    assert.throws(() => request.parseSuggestions({keys: [{...key, group: 'misc'}], lists: {}}), {name: 'SearchShapeError'});
    assert.throws(() => request.parseSuggestions({keys: [{...key, group: undefined}], lists: {}}), {name: 'SearchShapeError'});
    assert.throws(() => request.parseSuggestions({keys: [{...key, matchesFieldNames: 1}], lists: {}}),
        {name: 'SearchShapeError'});
    assert.throws(() => request.parseSuggestions({keys: [], lists: {by: [{value: 1, label: '', hint: ''}]}}),
        {name: 'SearchShapeError'});
    assert.throws(() => request.parseSuggestions({keys: [], lists: [1]}), {name: 'SearchShapeError'});
    assert.throws(() => request.parseSuggestions({keys: []}), {name: 'SearchShapeError'});

    assert.equal(request.refusal({error: 'The search range needs a start and an end.'}),
        'The search range needs a start and an end.');
    assert.equal(request.refusal({error: ''}), null);
    assert.equal(request.refusal('oops'), null);
});
