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

test('diff: words, whitespace and tags; neighbours of one kind joined', () => {
    const diff = load('search/diff');
    const result = diff.words('<p>Big enough to haul a ball</p>', '<p>Big enough to carry a ball and shoes</p>');

    assert.deepEqual(plain(result.parts), [
        {op: 'equal', text: '<p>Big enough to '},
        {op: 'delete', text: 'haul'},
        {op: 'insert', text: 'carry'},
        {op: 'equal', text: ' a ball'},
        {op: 'insert', text: ' and shoes'},
        {op: 'equal', text: '</p>'}
    ]);
    assert.equal(result.edits, 3);
    assert.equal(result.capped, false);
    assert.deepEqual(plain(result.rest), {before: '', after: ''});

    assert.deepEqual(plain(diff.words(null, 'new text').parts), [{op: 'insert', text: 'new text'}]);
    assert.deepEqual(plain(diff.words('old', '').parts), [{op: 'delete', text: 'old'}]);
    assert.deepEqual(plain(diff.words('', null).parts), []);
});

test('diff: each side is compared over its first 600 tokens, the rest returned as it is', () => {
    const diff = load('search/diff');
    const words = (count, word) => Array.from({length: count}, (unused, index) => word + index).join(' ');
    const before = words(2000, 'a');
    const after = words(2000, 'b');

    const started = process.hrtime.bigint();
    const result = diff.words(before, after);
    const elapsedMs = Number(process.hrtime.bigint() - started) / 1e6;

    assert.equal(diff.MAX_TOKENS, 600);
    assert.equal(result.capped, true);
    const compared = result.parts.map((part) => part.text).join('');
    assert.ok(compared.length < before.length + after.length);
    assert.equal(result.parts.filter((part) => part.op !== 'insert').map((part) => part.text).join('') + result.rest.before,
        before, 'what was compared of the old value and the rest make up the whole value');
    assert.equal(result.parts.filter((part) => part.op !== 'delete').map((part) => part.text).join('') + result.rest.after,
        after);
    assert.ok(result.rest.after.startsWith('b300'), '600 tokens are 300 words and 300 spaces');
    assert.ok(elapsedMs < 1000, 'the work is bounded: ' + elapsedMs + ' ms');

    assert.equal(diff.words(words(299, 'a'), words(299, 'a')).capped, false);
});

test('diff: JSON lists gain and lose items; repeated items count; anything else is not a list', () => {
    const diff = load('search/diff');

    assert.deepEqual(plain(diff.array(
        JSON.stringify(['res/pages', 'res/items', 'res/blocks']),
        JSON.stringify(['res/pages', 'res/blocks', 'res/media'])
    )), {
        ok: true,
        added: ['res/media'],
        removed: ['res/items'],
        same: ['res/pages', 'res/blocks']
    });
    assert.deepEqual(plain(diff.array('["a","a","b"]', '["a","b","b"]')),
        {ok: true, added: ['b'], removed: ['a'], same: ['a', 'b']});
    assert.deepEqual(plain(diff.array('[1,{"x":2},null]', '[{"x":2},true]')),
        {ok: true, added: ['true'], removed: ['1', 'null'], same: ['{"x":2}']});
    assert.deepEqual(plain(diff.array(null, '["a"]')), {ok: true, added: ['a'], removed: [], same: []});
    assert.deepEqual(plain(diff.array('', '')), {ok: true, added: [], removed: [], same: []});
    assert.deepEqual(plain(diff.array('["__proto__"]', '["__proto__","constructor"]')),
        {ok: true, added: ['constructor'], removed: [], same: ['__proto__']});

    assert.equal(diff.array('{"a":1}', '[]').ok, false, 'an object is not a list');
    assert.equal(diff.array('[]', '"text"').ok, false);
    assert.equal(diff.array('[1,2', '[]').ok, false, 'a cut list does not decode');
    assert.deepEqual(plain(diff.array('42', '[]')), {ok: false, added: [], removed: [], same: []});
});

/**
 * A result as request.parsePage returns it
 */
function event(overrides) {
    return Object.assign({
        id: 1,
        occurredAtMs: 1000,
        channel: 'records',
        correlationId: 'c7a1e9',
        siblingCount: 0,
        actor: {type: 'admin', name: 'john', viaClient: null, viaVersion: null, viaChannel: null},
        action: 'Saved',
        outcome: 'success',
        message: null,
        durationMs: null,
        ip: '10.0.4.21',
        subject: {type: 'record', id: '1245', label: 'Record 1245'},
        verb: 'changed',
        detail: null,
        detailUrl: '/admin/activitylog/activity/view/id/1/'
    }, overrides);
}

function field(overrides) {
    return Object.assign({
        name: 'price', label: 'Price', scope: 'Default Store View', before: '34.00', after: '39.00', kind: 'short',
        changed: true, automatic: false, truncated: false, beforeLength: 5,
        afterLength: 5
    }, overrides);
}

function detail(overrides) {
    return Object.assign({
        what: null, fieldCount: 0, automaticCount: 0, subjectTypeLabel: 'Record', subjectLabel: null,
        subjectUrl: '/admin/record/edit/id/1245/',
        fields: [], moreFieldCount: 0, unchangedCount: 0, unchangedRecorded: true
    }, overrides);
}

test('sentence: one short change reads inline', () => {
    const sentence = load('search/sentence');
    const model = plain(sentence.build(event({
        detail: detail({what: 'Price', fieldCount: 1, fields: [field()]})
    })));

    assert.equal(model.verb, 'changed');
    assert.deepEqual(model.what, {type: 'field', label: 'Price', count: null});
    assert.deepEqual(model.subject, {typeLabel: 'Record', label: 'Record 1245', url: '/admin/record/edit/id/1245/'});
    assert.deepEqual(model.scope, {type: 'one', label: 'Default Store View'});
    assert.deepEqual(model.inline, {before: {text: '34.00', empty: false}, after: {text: '39.00', empty: false}});
    assert.deepEqual(model.rows, []);
    assert.equal(model.denied, false);
    assert.equal(model.actor.via, null);
    assert.equal(model.actor.viaChannel, null);
});

test('sentence: several fields are counted and listed, across scopes; empty values are marked', () => {
    const sentence = load('search/sentence');
    const model = plain(sentence.build(event({
        detail: detail({
            fieldCount: 2,
            fields: [
                field({name: 'name', label: 'Name', before: 'Bags', after: 'Bags & Luggage'}),
                field({name: 'is_anchor', label: 'is_anchor', scope: 'All Store Views', before: null, after: ''})
            ]
        })
    })));

    assert.deepEqual(model.what, {type: 'fields', label: null, count: 2});
    assert.equal(model.inline, null);
    assert.deepEqual(model.scope, {type: 'several', label: null});
    assert.equal(model.rows.length, 2);
    assert.equal(model.rows[0].showName, true);
    assert.equal(model.rows[1].showName, false, 'a field without a label shows its code once');
    assert.deepEqual(model.rows[1].before, {text: '', empty: true});
    assert.deepEqual(model.rows[1].after, {text: '', empty: true});
    assert.deepEqual(model.rows[0].view, {type: 'short'});
});

test('sentence: fields set automatically are not counted with what the actor changed, and their rows say so', () => {
    const sentence = load('search/sentence');
    const labels = (name) => field({name: name, label: name, before: 'Joust Duffle Bag', after: null, automatic: true});
    const one = plain(sentence.build(event({
        detail: detail({
            what: 'Price',
            fieldCount: 4,
            automaticCount: 3,
            fields: [field(), labels('image_label'), labels('small_image_label'), labels('thumbnail_label')]
        })
    })));

    assert.equal(one.automatic, false, 'the actor changed the price');
    assert.deepEqual(one.what, {type: 'field', label: 'Price', count: null});
    assert.deepEqual(one.inline, {before: {text: '34.00', empty: false}, after: {text: '39.00', empty: false}},
        'the one value asked for still reads inline');
    assert.deepEqual(one.rows.map((row) => [row.name, row.automatic]),
        [['image_label', true], ['small_image_label', true], ['thumbnail_label', true]]);

    const two = plain(sentence.build(event({
        detail: detail({
            fieldCount: 4,
            automaticCount: 2,
            fields: [field(), field({name: 'cost', label: 'Cost'}), labels('image_label'), labels('thumbnail_label')]
        })
    })));

    assert.deepEqual(two.what, {type: 'fields', label: null, count: 2}, 'only the two fields asked for are counted');
    assert.equal(two.inline, null);
    assert.equal(two.rows.length, 4, 'every changed field keeps its row');
});

test('sentence: a change in which every field was set automatically is the system\'s doing', () => {
    const sentence = load('search/sentence');
    const automatic = (overrides) => field(Object.assign({automatic: true}, overrides));
    const several = plain(sentence.build(event({
        detail: detail({
            fieldCount: 23,
            automaticCount: 23,
            fields: [automatic({name: 'image_label', label: 'Image Label', before: 'Bag', after: null}),
                automatic({name: 'gift_message_available', label: 'Allow Gift Message', before: null, after: 'No'})],
            moreFieldCount: 21
        })
    })));

    assert.equal(several.automatic, true);
    assert.deepEqual(several.what, {type: 'fields', label: null, count: 23}, 'the fields set automatically are counted');
    assert.deepEqual(several.rows.map((row) => row.automatic), [true, true]);

    const one = plain(sentence.build(event({
        detail: detail({
            what: 'Allow Gift Message',
            fieldCount: 1,
            automaticCount: 1,
            fields: [automatic({name: 'gift_message_available', label: 'Allow Gift Message', before: null, after: 'No'})]
        })
    })));

    assert.equal(one.automatic, true);
    assert.deepEqual(one.what, {type: 'field', label: 'Allow Gift Message', count: null});
    assert.deepEqual(one.inline, {before: {text: '', empty: true}, after: {text: 'No', empty: false}});

    const created = plain(sentence.build(event({
        verb: 'created',
        detail: detail({fieldCount: 1, automaticCount: 1, fields: [automatic({before: null})]})
    })));

    assert.equal(created.automatic, false, 'only a change of an existing record can be the system\'s doing');
});

test('sentence: one change with fields beyond the page is not inline', () => {
    const sentence = load('search/sentence');
    const model = sentence.build(event({
        detail: detail({what: 'Price', fieldCount: 21, fields: [field()], moreFieldCount: 20})
    }));

    assert.equal(model.inline, null);
    assert.equal(model.rows.length, 1);
    assert.equal(model.moreFieldCount, 20);
});

test('sentence: a record created names its field count and lists the values', () => {
    const sentence = load('search/sentence');
    const model = plain(sentence.build(event({
        verb: 'created',
        detail: detail({fieldCount: 24, fields: [field({before: null, after: '32.00'})], moreFieldCount: 23})
    })));

    assert.equal(model.what, null);
    assert.equal(model.createdWith, 24);
    assert.equal(model.inline, null, 'a creation is never one inline value');
    assert.deepEqual(model.rows[0].before, {text: '', empty: true});
});

test('sentence: long text, JSON lists and masked secrets each get their view', () => {
    const sentence = load('search/sentence');
    const model = plain(sentence.build(event({
        detail: detail({
            fieldCount: 4,
            fields: [
                field({name: 'description', label: 'Description', kind: 'long', before: 'a b c', after: 'a x c'}),
                field({name: 'resources', label: 'Resources', kind: 'json', before: '["a","b"]', after: '["b","c"]'}),
                field({name: 'endpoints', label: 'Endpoints', kind: 'json', before: '{"a":1}', after: '[]'}),
                field({name: 'secret', label: 'Secret', kind: 'masked', before: '••••••', after: '••••••'})
            ]
        })
    })));

    assert.equal(model.rows[0].view.type, 'long');
    assert.equal(model.rows[0].view.diff.edits, 2);
    assert.deepEqual(model.rows[1].view, {type: 'json', added: ['c'], removed: ['a'], same: ['b']});
    assert.equal(model.rows[2].view.type, 'long', 'a JSON field whose side is no list falls back to long text');
    assert.deepEqual(model.rows[3].view, {type: 'masked'});
    assert.equal(model.rows[3].before, null, 'a masked value is never handed to the page');
    assert.equal(model.rows[3].after, null);
});

test('sentence: fields recorded unchanged are kept apart', () => {
    const sentence = load('search/sentence');
    const model = plain(sentence.build(event({
        detail: detail({
            what: 'Quantity',
            fieldCount: 1,
            fields: [
                field({name: 'qty', label: 'Quantity', before: '120', after: '85'}),
                field({name: 'status', label: 'Status', before: 'Enabled', after: 'Enabled', changed: false})
            ],
            unchangedCount: 7,
            unchangedRecorded: true
        })
    })));

    assert.notEqual(model.inline, null);
    assert.deepEqual(model.rows, []);
    assert.equal(model.unchanged.count, 7);
    assert.deepEqual(model.unchanged.rows.map((row) => row.name), ['status']);
    assert.equal(model.unchanged.recorded, true);
    assert.equal(model.unchanged.partial, true, 'one row of the seven recorded: the result carries only part');

    const whole = plain(sentence.build(event({detail: detail({
        fields: [field({name: 'status', label: 'Status', before: 'On', after: 'On', changed: false})],
        unchangedCount: 1,
        unchangedRecorded: true
    })})));
    assert.equal(whole.unchanged.partial, false, 'every recorded row is carried');
});

test('sentence: fields compared without recorded rows are a number only', () => {
    const sentence = load('search/sentence');
    const model = plain(sentence.build(event({
        detail: detail({
            what: 'Quantity',
            fieldCount: 1,
            fields: [field({name: 'qty', label: 'Quantity', before: '120', after: '85'})],
            unchangedCount: 47,
            unchangedRecorded: false
        })
    })));

    assert.deepEqual(model.unchanged, {count: 47, rows: [], recorded: false, partial: false},
        'no rows were recorded, so none are missing');
});

test('sentence: an activity no module describes says what it performed', () => {
    const sentence = load('search/sentence');
    const model = plain(sentence.build(event({
        channel: 'admin',
        verb: 'performed',
        action: 'section/item/save',
        outcome: 'denied',
        actor: {type: 'integration', name: 'ERP sync', viaClient: 'Client', viaVersion: '1.2', viaChannel: 'zz_agents'},
        subject: null,
        durationMs: 412,
        siblingCount: 3
    })));

    assert.equal(model.verb, 'performed');
    assert.deepEqual(model.what, {type: 'action', label: 'section/item/save', count: null});
    assert.deepEqual(model.subject, {typeLabel: null, label: null, url: null});
    assert.equal(model.denied, true);
    assert.equal(model.actor.via, 'Client 1.2');
    assert.equal(model.actor.viaChannel, 'zz_agents', 'the channel the client acted through, as its code');
    assert.equal(model.scope, null);
    assert.deepEqual(model.unchanged, {count: 0, rows: [], recorded: true, partial: false});
    assert.equal(model.siblingCount, 3);
    assert.equal(model.durationMs, 412);
});

test('sentence: a sibling line names the action, subject and changed fields', () => {
    const sentence = load('search/sentence');

    assert.deepEqual(plain(sentence.sibling(event({
        detail: detail({fields: [field(), field({name: 'qty', label: 'Quantity', changed: false})]})
    }))), {
        id: 1, occurredAtMs: 1000, channel: 'records', action: 'Saved', subjectLabel: 'Record 1245', durationMs: null,
        fieldLabels: ['Price'], outcome: 'success', detailUrl: '/admin/activitylog/activity/view/id/1/'
    });
    assert.deepEqual(plain(sentence.sibling(event({subject: null})).fieldLabels), []);
});

test('sentence: the subject is named as its module names it, else as the journal recorded it', () => {
    const sentence = load('search/sentence');
    const recorded = event({subject: {type: 'record', id: '1245', label: 'Record SKU-1 · Bag'}});

    assert.equal(sentence.build(Object.assign({}, recorded, {detail: detail({subjectLabel: 'SKU-1 · Bag'})}))
        .subject.label, 'SKU-1 · Bag');
    assert.equal(sentence.build(Object.assign({}, recorded, {detail: detail()})).subject.label, 'Record SKU-1 · Bag');
    assert.equal(sentence.build(recorded).subject.label, 'Record SKU-1 · Bag', 'without details');
    assert.equal(sentence.sibling(Object.assign({}, recorded, {detail: detail({subjectLabel: 'SKU-1 · Bag'})}))
        .subjectLabel, 'Record SKU-1 · Bag', 'a sibling line names no kind, so it keeps the recorded name');
});

test('sentence: builds from a page the response parser accepted', () => {
    const request = load('search/request');
    const sentence = load('search/sentence');
    const page = request.parsePage({
        events: [event({detail: detail({what: 'Price', fieldCount: 1, fields: [field()]})})],
        nextCursor: null, narrowed: true, total: {count: 1, atLeast: false}, outside: null, latestOutsideMs: null,
        overall: null, journalEmpty: false
    });

    assert.deepEqual(plain(sentence.build(page.events[0]).inline.after), {text: '39.00', empty: false});
    assert.equal(page.narrowed, true);
});

test('sentence: rows matching a chip of a key that names fields are highlighted', () => {
    const sentence = load('search/sentence');
    const keys = [
        {key: 'property', matchesFieldNames: true},
        {key: 'setting', matchesFieldNames: true},
        {key: 'by', matchesFieldNames: false}
    ];
    const terms = sentence.fieldTerms([
        {key: 'property', value: 'Special Price'},
        {key: 'setting', value: 'section/group'},
        {key: 'by', value: 'qty'}
    ], keys);

    assert.deepEqual(plain(terms), ['special price', 'section/group'], 'only keys that name fields count');

    const model = plain(sentence.build(event({
        detail: detail({
            fieldCount: 4,
            fields: [
                field({name: 'special_price', label: 'Special Price'}),
                field({name: 'section/group/enabled', label: 'Enabled'}),
                field({name: 'qty', label: 'Quantity'}),
                field({name: 'price', label: 'Price'})
            ]
        })
    }), terms));

    assert.deepEqual(model.rows.map((row) => row.highlighted), [true, true, false, false],
        'the label equals the value, or the code contains it; "Price" neither contains nor equals "special price"');

    const byName = plain(sentence.build(event({
        detail: detail({fieldCount: 2, fields: [field({name: 'special_price', label: 'Special Price'}), field({name: 'cost', label: 'Cost'})]})
    }), sentence.fieldTerms([{key: 'property', value: 'PRICE'}], keys)));
    assert.deepEqual(byName.rows.map((row) => row.highlighted), [true, false], 'the code contains the value, ignoring case');

    const exact = plain(sentence.build(event({
        detail: detail({fieldCount: 2, fields: [field({name: 'special_price', label: 'Special Price'}), field()]})
    }), sentence.fieldTerms([{key: 'property', value: '"price"'}], keys)));
    assert.deepEqual(exact.rows.map((row) => row.highlighted), [false, true], 'a quoted value names one code exactly');

    const none = plain(sentence.build(event({
        detail: detail({fieldCount: 2, fields: [field(), field({name: 'qty', label: 'Quantity'})]})
    })));
    assert.deepEqual(none.rows.map((row) => row.highlighted), [false, false], 'nothing is highlighted without terms');
});
