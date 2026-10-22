/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const {load} = require('./amd.cjs');
const {FakeDocument, builder, fire} = require('./dom-fake.cjs');

/** Values built inside the module's own realm, as plain data of this one */
const plain = (value) => JSON.parse(JSON.stringify(value));
const flush = () => new Promise((resolve) => setImmediate(resolve));

const utc = (...parts) => Date.UTC(...parts);
const NOW = utc(2026, 9, 22, 12, 30);
const zone = () => load('search/zone').create('Europe/Copenhagen', 'en_GB');

/**
 * The timeline config the assets print, with readable wording where the tests read it
 */
function timelineConfig() {
    const wording = {};

    load('timeline/config').WORDING.forEach((name) => {
        wording[name] = name;
    });
    Object.assign(wording, {
        via: 'via', on: 'on', empty: '(empty)', fields: '%1 fields', siblings: '%1 more in this request',
        dayToday: 'Today · %1', dayYesterday: 'Yesterday · %1', dayEventsOne: '%1 event', dayEventsMany: '%1 events',
        changeOne: 'change', changeMany: 'changes', showingOf: 'Showing %1 of %2', atLeast: '%1+',
        loadMore: 'Load %1 more', loadedOf: 'Loaded %1 of %2', newestFirst: 'newest first',
        noResultsTitle: 'No changes match in %1', outsideMany: '%1 matching changes are outside. The latest is %2.',
        narrow: 'Only the newest %1 were searched.', showFullDiff: 'Show full diff', collapse: 'Collapse',
        longMeta: '%1 → %2 chars · %3 edits', jsonMeta: '%1 added · %2 removed · %3 unchanged',
        showUnchanged: 'Show unchanged', hideUnchanged: 'Hide unchanged',
        unchangedOne: '%1 unchanged field recorded', unchangedMany: '%1 unchanged fields recorded',
        unchangedCountOne: '%1 other field unchanged', unchangedCountMany: '%1 other fields unchanged',
        moreFields: 'and %1 more fields.', createdWithOne: 'with %1 field', createdWith: 'with %1 fields', severalScopes: 'several scopes',
        unchangedPartial: 'Showing %1 of %2.', openDetails: 'Open details', siblingsCapped: 'Only the first %1 are listed.',
        widen: 'Widen', removeLast: 'Remove last filter', clearAll: 'Clear all', retry: 'Retry'
    });

    return {
        wording: wording,
        verbs: {changed: 'changed', created: 'created', deleted: 'deleted', performed: 'performed',
            tried_to_change: 'tried to change', tried_to_delete: 'tried to delete'},
        channels: {records: {label: 'Record change', chart: '#000', bg: '#eee', fg: '#111'}},
        actorTypes: {admin: 'Admin'},
        outcomes: {success: 'Success', failure: 'Failure', denied: 'Denied'},
        candidateLimit: 10000
    };
}

/**
 * The page with the timeline's assets as the layout handle prints them, and an element to mount in
 *
 * @returns {{doc: FakeDocument, root: Object}}
 */
function page(config = timelineConfig()) {
    const doc = new FakeDocument();
    const el = builder(doc);
    const slot = (tag, name, attrs = {}, children = []) => el(tag, Object.assign({'data-slot': name}, attrs), children);
    const template = (name, child) => el('template', {'data-template': name}, [child]);
    const root = el('div', {'data-role': 'timeline-view'});

    doc.body.appendChild(root);
    doc.body.appendChild(el('div', {'data-role': 'activity-timeline-assets', hidden: true}, [
        el('script', {'data-role': 'activity-timeline-config', text: JSON.stringify(config)}),
        template('results', el('div', {}, [
            slot('div', 'head', {hidden: true}, [slot('strong', 'count'), slot('span', 'noun'), slot('span', 'span')]),
            slot('div', 'notice', {hidden: true}),
            slot('div', 'list'),
            slot('div', 'paging', {hidden: true}, [
                slot('button', 'more', {'data-action': 'more', type: 'button'}),
                slot('span', 'loaded')
            ]),
            slot('div', 'status'),
            slot('span', 'live')
        ])),
        template('day', el('section', {}, [el('div', {}, [slot('span', 'label'), slot('span', 'count')])])),
        template('item', el('article', {}, [
            el('div', {}, [slot('div', 'when', {}, [slot('span', 'clock'), slot('span', 'ms')]), slot('span', 'channel')]),
            el('div', {}, [
                slot('p', 'sentence'),
                slot('div', 'rows', {hidden: true}),
                el('div', {}, [
                    slot('span', 'actor-type'),
                    slot('button', 'unchanged', {hidden: true, type: 'button'}),
                    slot('span', 'unchanged-count', {hidden: true}),
                    slot('button', 'siblings', {hidden: true, type: 'button', 'aria-expanded': 'false'},
                        [slot('span', 'siblings-label')]),
                    slot('span', 'correlation'),
                    slot('a', 'details')
                ]),
                slot('div', 'sibling-list', {hidden: true})
            ])
        ])),
        template('row', el('div', {}, [
            el('div', {}, [slot('div', 'label'), slot('div', 'name'), slot('div', 'scope'),
                slot('span', 'automatic', {hidden: true})]),
            slot('div', 'value')
        ])),
        template('masked', el('span', {}, [slot('span', 'tag'), slot('span', 'note')])),
        template('sibling', el('div', {}, [slot('span', 'clock'), slot('span', 'channel'), slot('a', 'action'),
            slot('span', 'meta'), slot('span', 'outcome')])),
        template('skeleton', el('div', {'data-part': 'skeleton'})),
        template('state', el('div', {'data-part': 'state'}, [slot('div', 'title'), slot('div', 'text'),
            slot('div', 'actions')])),
        template('empty-log', el('div', {'data-part': 'empty-log'}, [slot('div', 'title'), slot('div', 'text'),
            slot('div', 'examples')])),
        template('example', el('button', {type: 'button'}, [slot('span', 'title'), slot('span', 'chips')]))
    ]));

    return {doc, root};
}

/**
 * A search port whose answers the test gives
 */
function fakeSearch() {
    const calls = [];

    return {
        calls,
        page(query, signal) {
            return new Promise((resolve, reject) => {
                calls.push({query, signal, resolve, reject});
            });
        }
    };
}

function event(overrides) {
    return Object.assign({
        id: 1,
        occurredAtMs: utc(2026, 9, 22, 10, 5, 9, 412),
        channel: 'records',
        correlationId: 'c7a1e9d0-55aa-4c1b-9f00-000000000001',
        siblingCount: 0,
        actor: {type: 'admin', name: 'john', viaClient: null, viaVersion: null, viaChannel: null},
        action: 'Saved',
        outcome: 'success',
        message: null,
        durationMs: 120,
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

function resultPage(overrides) {
    return Object.assign({
        events: [],
        nextCursor: null,
        narrowed: false,
        total: {count: 0, atLeast: false},
        outside: null,
        latestOutsideMs: null,
        overall: null,
        journalEmpty: false
    }, overrides);
}

const SEARCH = Object.freeze({
    chips: [{key: 'by', value: 'john'}],
    range: {preset: '7d', from: utc(2026, 9, 15, 22), to: NOW},
    changesOnly: true
});

/**
 * Mounts the timeline over the fake page
 */
function mounted(overrides = {}) {
    const {config, ...options} = overrides;
    const {doc, root} = page(config);
    const search = fakeSearch();
    const asked = [];
    const actions = [];
    const reported = [];
    const pages = [];
    const timeline = load('timeline/timeline').mount(root, Object.assign({
        assets: load('timeline/assets').read(doc),
        search: search,
        clock: {now: () => NOW},
        zone: zone(),
        locale: 'en_GB',
        keys: [{key: 'field', matchesFieldNames: true}],
        report: (failure) => reported.push(failure),
        describeRange: (range) => ({label: 'Last 7 days', span: range.from + ' – ' + range.to}),
        searchLink: (chips) => '/admin/activitylog/?' + chips.map((chip) => chip.key + '=' + chip.value).join('&'),
        ask: (criterion) => asked.push(criterion),
        actions: {
            widen: () => actions.push('widen'),
            removeLast: () => actions.push('removeLast'),
            clearAll: () => actions.push('clearAll'),
            example: (index) => actions.push('example ' + index)
        },
        examples: [{title: 'Prices changed', chips: [{key: 'field', value: 'price'}], rangeLabel: 'Last 30 days'}],
        onPage: (shown, first) => pages.push({shown, first})
    }, options));
    const slot = (name, within = root) => within.querySelector('[data-slot="' + name + '"]');

    return {doc, root, search, asked, actions, reported, pages, timeline, slot};
}

test('assets: the config and templates the handle printed are read once; a page without them is refused', () => {
    const {doc} = page();
    const assets = load('timeline/assets').read(doc);

    assert.equal(assets.config.verbs.changed, 'changed');
    assert.equal(assets.clone('day').tagName, 'SECTION');
    assert.notEqual(assets.clone('item'), assets.clone('item'), 'every clone is a fresh element');
    assert.throws(() => assets.clone('nothing'), /no template "nothing"/);
    assert.throws(() => load('timeline/assets').read(new FakeDocument()), /does not include the timeline assets/);

    const broken = page(Object.assign(timelineConfig(), {verbs: {}}));
    assert.throws(() => load('timeline/assets').read(broken.doc), /config is malformed/);
});

test('timeline: a search shows skeletons, then its activities under the store\'s days with the count line', async () => {
    const t = mounted();
    const older = utc(2026, 9, 21, 8, 0, 0, 7);

    t.timeline.show(SEARCH);
    assert.ok(t.root.querySelector('[data-part="skeleton"]'), 'loading shows skeletons, not a spinner');
    assert.equal(t.root.querySelector('[aria-busy="true"]') !== null, true);
    assert.equal(t.slot('live').textContent, 'loading');
    assert.deepEqual(plain(t.search.calls[0].query), {
        range: {from: SEARCH.range.from, to: NOW},
        chips: [{key: 'by', value: 'john'}],
        changesOnly: true,
        cursor: null,
        limit: 50
    });

    t.search.calls[0].resolve(resultPage({
        events: [
            event({id: 3, detail: detail({what: 'Price', fieldCount: 1, fields: [field()]})}),
            event({id: 2, occurredAtMs: utc(2026, 9, 22, 9)}),
            event({id: 1, occurredAtMs: older})
        ],
        total: {count: 184302, atLeast: false},
        nextCursor: 'next-1'
    }));
    await flush();

    assert.equal(t.root.querySelector('[data-part="skeleton"]'), null);
    assert.equal(t.slot('live').textContent, '');
    const sections = t.slot('list').children;
    assert.equal(sections.length, 2);
    assert.equal(t.slot('label', sections[0]).textContent, 'Today · Thursday, 22 October 2026');
    assert.equal(t.slot('count', sections[0]).textContent, '2 events');
    assert.equal(t.slot('label', sections[1]).textContent, 'Yesterday · Wednesday, 21 October 2026');
    assert.equal(t.slot('count', sections[1]).textContent, '1 event');

    assert.equal(t.slot('head').hidden, false);
    assert.equal(t.slot('count').textContent, 'Showing 3 of 184,302');
    assert.equal(t.slot('noun').textContent, 'changes');
    assert.equal(t.slot('span').textContent, ' · ' + SEARCH.range.from + ' – ' + NOW + ' · newest first');
    assert.equal(t.slot('paging').hidden, false);
    assert.equal(t.slot('more').textContent, 'Load 50 more');
    assert.equal(t.slot('loaded').textContent, 'Loaded 3 of 184,302');
    assert.equal(t.pages.length, 1);
    assert.equal(t.pages[0].shown, SEARCH, 'the page is told which search the counts are of');
});

test('timeline: one sentence per activity — actor, verb, what, subject, scope and the value inline', async () => {
    const t = mounted();

    t.timeline.show(SEARCH);
    t.search.calls[0].resolve(resultPage({
        events: [event({
            actor: {type: 'admin', name: 'john', viaClient: 'Desk Client', viaVersion: '1.2', viaChannel: null},
            detail: detail({what: 'Price', fieldCount: 1, fields: [field({before: ''})]})
        })],
        total: {count: 1, atLeast: false}
    }));
    await flush();

    const item = t.root.querySelector('[data-activity="1"]');
    assert.equal(t.slot('sentence', item).textContent,
        'john via Desk Client 1.2 changed Price on Record Record 1245 (Default Store View): (empty) → 39.00');
    assert.equal(t.slot('clock', item).textContent, '12:05:09');
    assert.equal(t.slot('ms', item).textContent, '.412');
    assert.equal(t.slot('when', item).getAttribute('title'), '2 hours ago');
    assert.equal(t.slot('channel', item).textContent, 'Record change');
    assert.deepEqual(t.slot('channel', item).style, {backgroundColor: '#eee', color: '#111'});
    assert.equal(t.slot('rows', item).hidden, true, 'one short value reads inline, without rows');
    assert.equal(t.slot('actor-type', item).textContent, 'Admin · 10.0.4.21');
    assert.equal(t.slot('details', item).getAttribute('href'), '/admin/activitylog/activity/view/id/1/');
    assert.equal(t.slot('count').textContent, '1');
    assert.equal(t.slot('noun').textContent, 'change');
    assert.equal(t.slot('paging').hidden, true, 'nothing more to load');

    const subject = item.querySelector('[href="/admin/record/edit/id/1245/"]');
    assert.equal(subject.textContent, 'Record 1245', 'the subject links to the record');
    const actor = item.querySelector('[data-action="actor"]');
    assert.equal(actor.getAttribute('href'), '/admin/activitylog/?by=john');
});

test('timeline: the channel a client acted through is a badge — the registered channel\'s name and colours, or its code', async () => {
    const t = mounted();
    const via = (viaClient, viaChannel) => ({type: 'admin', name: 'john', viaClient, viaVersion: null, viaChannel});

    t.timeline.show(SEARCH);
    t.search.calls[0].resolve(resultPage({
        events: [
            event({id: 3, actor: via('Desk Client', 'records'), subject: null}),
            event({id: 2, actor: via('Desk Client', 'zz_agents'), subject: null}),
            event({id: 1, actor: via(null, 'zz_agents'), subject: null})
        ],
        total: {count: 3, atLeast: false}
    }));
    await flush();

    const sentence = (id) => t.slot('sentence', t.root.querySelector('[data-activity="' + id + '"]'));
    const badge = (id) => sentence(id).children.find((part) => part.className.includes('activity-stream__via'));

    assert.equal(sentence(3).textContent, 'john via Record change Desk Client changed Saved');
    assert.deepEqual(badge(3).style, {backgroundColor: '#eee', color: '#111'});
    assert.equal(badge(3).classList.contains('activity-stream__via--unknown'), false);
    assert.equal(sentence(2).textContent, 'john via ZZ_AGENTS Desk Client changed Saved');
    assert.equal(badge(2).classList.contains('activity-stream__via--unknown'), true, 'an unregistered channel is neutral');
    assert.deepEqual(badge(2).style, {});
    assert.equal(sentence(1).textContent, 'john via ZZ_AGENTS changed Saved');
});

test('timeline: a record created names its field count, and in a locale writing 12-hour times the clock stays 24-hour', async () => {
    const t = mounted({locale: 'en_US', zone: load('search/zone').create('Europe/Copenhagen', 'en_US')});

    t.timeline.show(SEARCH);
    t.search.calls[0].resolve(resultPage({
        events: [
            event({id: 2, verb: 'created', detail: detail({fieldCount: 1, fields: [field({before: null})]})}),
            event({id: 1, verb: 'created', detail: detail({fieldCount: 3, fields: [field({before: null})]})})
        ],
        total: {count: 2, atLeast: false}
    }));
    await flush();

    const sentence = (id) => t.slot('sentence', t.root.querySelector('[data-activity="' + id + '"]')).textContent;
    assert.equal(sentence(2), 'john created Record Record 1245 (Default Store View) with 1 field');
    assert.equal(sentence(1), 'john created Record Record 1245 (Default Store View) with 3 fields');
    assert.equal(t.slot('clock', t.root.querySelector('[data-activity="2"]')).textContent, '12:05:09');
});

test('timeline: rows for several fields — word diff, JSON items, masked, unchanged — each expands in place', async () => {
    const t = mounted();
    const longBefore = 'The quick brown fox jumps';
    const longAfter = 'The quick red fox jumps high';

    t.timeline.show(Object.assign({}, SEARCH, {chips: [{key: 'field', value: 'price'}]}));
    t.search.calls[0].resolve(resultPage({
        events: [event({
            detail: detail({
                fieldCount: 4,
                unchangedCount: 2,
                fields: [
                    field(),
                    field({name: 'description', label: 'Description', kind: 'long', scope: 'Main Website',
                        before: longBefore,
                        after: longAfter, beforeLength: 2100, afterLength: 2240, truncated: true}),
                    field({name: 'tags', label: 'Tags', kind: 'json', scope: null, before: '["a","b"]',
                        after: '["b","c"]'}),
                    field({name: 'secret', label: 'Secret', kind: 'masked', before: null, after: null}),
                    field({name: 'sku', label: 'SKU', changed: false, before: 'X', after: 'X'})
                ],
                moreFieldCount: 3
            })
        })],
        total: {count: 1, atLeast: false}
    }));
    await flush();

    const item = t.root.querySelector('[data-activity="1"]');
    const rows = t.slot('rows', item);
    assert.equal(rows.hidden, false);
    assert.equal(t.slot('sentence', item).textContent, 'john changed 4 fields on Record Record 1245 (several scopes)');
    assert.equal(rows.children.length, 5, 'four rows and the line of further fields');
    assert.equal(rows.children[0].classList.contains('activity-stream__row--highlighted'), true,
        'the row a field filter matches is highlighted');
    assert.equal(t.slot('value', rows.children[0]).textContent, '34.00 → 39.00');

    const long = rows.children[1];
    assert.equal(t.slot('value', long).querySelector('[data-part="long"]').textContent,
        'The quick brownred fox jumps high');
    assert.ok(t.slot('value', long).textContent.includes('2,100 → 2,240 chars · 3 edits'));
    assert.ok(t.slot('value', long).textContent.includes('truncated'), 'a value cut for transport says so');
    const diffButton = long.querySelector('[data-action="diff"]');
    fire(diffButton, 'click');
    assert.equal(diffButton.getAttribute('aria-expanded'), 'true');
    assert.equal(diffButton.textContent, 'Collapse');
    assert.equal(long.querySelector('[data-part="long"]').classList.contains('activity-stream__long--full'), true);
    fire(diffButton, 'click');
    assert.equal(diffButton.textContent, 'Show full diff');

    const json = rows.children[2];
    assert.equal(t.slot('scope', json).hidden, true);
    const sameLine = json.querySelector('[data-part="same"]');
    assert.equal(sameLine.hidden, true, 'unchanged items are collapsed');
    assert.ok(json.textContent.includes('− a') && json.textContent.includes('+ c'));
    assert.ok(json.textContent.includes('1 added · 1 removed · 1 unchanged'));
    fire(json.querySelector('[data-action="same"]'), 'click');
    assert.equal(sameLine.hidden, false);

    assert.equal(t.slot('value', rows.children[3]).textContent, 'maskedmaskedNote', 'a secret is never shown');
    assert.ok(rows.children[4].textContent.startsWith('and 3 more fields.'));

    const unchanged = t.slot('unchanged', item);
    assert.equal(unchanged.hidden, false);
    assert.equal(unchanged.textContent, '2 unchanged fields recorded');
    fire(unchanged, 'click');
    assert.equal(rows.querySelectorAll('div[data-unchanged]').length, 2, 'the unchanged rows the page carries, then a note');
    const partial = rows.querySelector('[data-part="unchanged-partial"]');
    assert.equal(partial.textContent, 'Showing 1 of 2. Open details',
        'fewer rows than were recorded: the list says how many it shows and links to the rest');
    assert.equal(partial.querySelector('a').getAttribute('href'), '/admin/activitylog/activity/view/id/1/');
    assert.equal(unchanged.textContent, 'Hide unchanged');
    fire(unchanged, 'click');
    assert.equal(rows.querySelectorAll('[data-unchanged]').length, 0);
    assert.equal(t.slot('unchanged-count', item).hidden, true, 'recorded rows are opened, not counted');
});

test('timeline: a field set automatically carries its tag, and a change made only of them reads as Magento\'s', async () => {
    const config = timelineConfig();
    Object.assign(config.wording, {
        automatic: 'set automatically', automaticUpdated: 'Magento updated', automaticDuring: 'during a save by'
    });
    const t = mounted({config});
    const label = (name) => field({name: name, label: name, before: 'Joust Duffle Bag', after: null, automatic: true});

    t.timeline.show(SEARCH);
    t.search.calls[0].resolve(resultPage({
        events: [
            event({id: 2, detail: detail({what: 'Price', fieldCount: 3, automaticCount: 2,
                fields: [field(), label('image_label'), label('thumbnail_label')]})}),
            event({id: 1, detail: detail({fieldCount: 2, automaticCount: 2,
                fields: [label('image_label'), field({name: 'gift_message_available', label: 'Allow Gift Message',
                    before: null, after: 'No', automatic: true})]})})
        ],
        total: {count: 2, atLeast: false}
    }));
    await flush();

    const asked = t.root.querySelector('[data-activity="2"]');
    assert.equal(t.slot('sentence', asked).textContent, 'john changed Price on Record Record 1245 (Default Store View): '
        + '34.00 → 39.00', 'the sentence is about the field the admin asked for');
    const askedRows = t.slot('rows', asked).children;
    assert.equal(askedRows.length, 2, 'the fields set automatically are listed under it');
    [0, 1].forEach((index) => {
        const tag = t.slot('automatic', askedRows[index]);
        assert.equal(tag.hidden, false);
        assert.equal(tag.textContent, 'set automatically');
        assert.equal(askedRows[index].getAttribute('data-automatic'), '');
    });

    const automatic = t.root.querySelector('[data-activity="1"]');
    assert.equal(t.slot('sentence', automatic).textContent,
        'Magento updated 2 fields on Record Record 1245 (Default Store View)');
    assert.equal(t.slot('actor-type', automatic).textContent, 'during a save by john · Admin · 10.0.4.21',
        'the admin whose save it happened in is named under the sentence');
    assert.equal(t.slot('actor-type', automatic).querySelector('[data-action="actor"]').getAttribute('data-value'),
        'john', 'and still filters by that admin');
    assert.equal(t.slot('automatic', t.slot('rows', automatic).children[1]).textContent, 'set automatically');
});

test('timeline: a field the admin asked for carries no tag', async () => {
    const t = mounted();

    t.timeline.show(SEARCH);
    t.search.calls[0].resolve(resultPage({
        events: [event({detail: detail({fieldCount: 2, fields: [field(), field({name: 'cost', label: 'Cost'})]})})],
        total: {count: 1, atLeast: false}
    }));
    await flush();

    const rows = t.slot('rows', t.root.querySelector('[data-activity="1"]')).children;
    assert.equal(t.slot('automatic', rows[0]).hidden, true);
    assert.equal(t.slot('automatic', rows[0]).textContent, '');
    assert.equal(rows[0].getAttribute('data-automatic'), null);
});

test('timeline: fields that kept their value without recorded rows are only counted, with nothing to open', async () => {
    const t = mounted();

    t.timeline.show(SEARCH);
    t.search.calls[0].resolve(resultPage({
        events: [
            event({id: 2, detail: detail({fieldCount: 1, unchangedCount: 47, unchangedRecorded: false,
                fields: [field()]})}),
            event({id: 1, detail: detail({fieldCount: 1, unchangedCount: 1, unchangedRecorded: false,
                fields: [field()]})})
        ],
        total: {count: 2, atLeast: false}
    }));
    await flush();

    const item = t.root.querySelector('[data-activity="2"]');
    assert.equal(t.slot('unchanged', item).hidden, true, 'no control offers rows that do not exist');
    const count = t.slot('unchanged-count', item);
    assert.equal(count.hidden, false);
    assert.equal(count.tagName, 'SPAN');
    assert.equal(count.textContent, '47 other fields unchanged');
    assert.equal(count.getAttribute('data-action'), null);
    assert.equal(t.slot('unchanged-count', t.root.querySelector('[data-activity="1"]')).textContent,
        '1 other field unchanged');
});

test('timeline: "N more in this request" loads the request once, without the activity itself', async () => {
    const t = mounted();

    t.timeline.show(SEARCH);
    t.search.calls[0].resolve(resultPage({events: [event({siblingCount: 2})], total: {count: 1, atLeast: false}}));
    await flush();

    const item = t.root.querySelector('[data-activity="1"]');
    const toggle = t.slot('siblings', item);
    const list = t.slot('sibling-list', item);
    assert.equal(toggle.hidden, false);
    assert.equal(t.slot('siblings-label', item).textContent, '2 more in this request');

    fire(toggle, 'click');
    assert.equal(toggle.getAttribute('aria-expanded'), 'true');
    assert.equal(list.hidden, false);
    assert.deepEqual(plain(t.search.calls[1].query), {
        range: {from: 0, to: NOW},
        chips: [{key: 'request', value: 'c7a1e9d0-55aa-4c1b-9f00-000000000001'}],
        changesOnly: false,
        cursor: null,
        limit: 200
    });
    t.search.calls[1].resolve(resultPage({events: [
        event({id: 9, channel: 'admin', action: 'POST catalog/product/save', subject: null, outcome: 'success',
            occurredAtMs: utc(2026, 9, 22, 10, 5, 9, 1)}),
        event({id: 1}),
        event({id: 8, outcome: 'denied', durationMs: null, detail: detail({fields: [field()]})})
    ]}));
    await flush();

    assert.equal(list.children.length, 2);
    assert.equal(t.slot('clock', list.children[0]).textContent, '12:05:09.001');
    assert.equal(t.slot('action', list.children[0]).textContent, 'POST catalog/product/save');
    assert.equal(t.slot('channel', list.children[0]).textContent, 'admin', 'a channel nobody describes keeps its code');
    assert.equal(t.slot('action', list.children[1]).textContent, 'Saved · Record 1245');
    assert.equal(t.slot('meta', list.children[1]).textContent, 'Price');
    assert.equal(t.slot('outcome', list.children[1]).textContent, 'Denied');

    assert.equal(list.querySelector('[data-part="siblings-capped"]'), null, 'the whole request is listed');

    fire(toggle, 'click');
    assert.equal(list.hidden, true);
    fire(toggle, 'click');
    assert.equal(list.hidden, false);
    assert.equal(t.search.calls.length, 2, 'a request is loaded once');
});

test('timeline: the list of a request\'s other activities says when it stops before the last of them', async () => {
    const t = mounted();

    t.timeline.show(SEARCH);
    t.search.calls[0].resolve(resultPage({events: [event({siblingCount: 250})], total: {count: 1, atLeast: false}}));
    await flush();

    const item = t.root.querySelector('[data-activity="1"]');
    fire(t.slot('siblings', item), 'click');
    t.search.calls[1].resolve(resultPage({
        events: Array.from({length: 200}, (unused, index) => event({id: 100 + index})),
        nextCursor: 'more'
    }));
    await flush();

    const list = t.slot('sibling-list', item);
    assert.equal(list.children.length, 201);
    assert.equal(list.querySelector('[data-part="siblings-capped"]').textContent, 'Only the first 200 are listed.');
});

test('timeline: a control that starts another search hands the focus to the results', async () => {
    const t = mounted();
    const frame = t.root.children[0];

    t.timeline.show(SEARCH);
    t.search.calls[0].resolve(resultPage({
        events: [],
        outside: {count: 3, atLeast: false},
        latestOutsideMs: utc(2026, 9, 10, 9)
    }));
    await flush();

    assert.equal(frame.getAttribute('tabindex'), '-1', 'the results can take the focus');
    ['widen', 'remove-last', 'clear-all'].forEach((action) => {
        const button = t.root.querySelector('button[data-action="' + action + '"]');

        button.focus();
        fire(button, 'click');
        assert.equal(t.doc.activeElement, frame, action + ' moves the focus to the results');
    });
    assert.deepEqual(t.actions, ['widen', 'removeLast', 'clearAll']);

    t.timeline.show(SEARCH);
    t.search.calls[1].reject({kind: 'failed'});
    await flush();
    const retry = t.root.querySelector('button[data-action="retry"]');
    retry.focus();
    fire(retry, 'click');
    assert.equal(t.root.querySelector('button[data-action="retry"]'), null, 'Retry is drawn away by the new search');
    assert.equal(t.doc.activeElement, frame, 'and the focus is on the results, not lost');
});

test('timeline: the actor and the request filter the search in place, alone; a modified click opens the link', async () => {
    const t = mounted();

    t.timeline.show(SEARCH);
    t.search.calls[0].resolve(resultPage({events: [event()], total: {count: 1, atLeast: false}}));
    await flush();

    const item = t.root.querySelector('[data-activity="1"]');
    const request = item.querySelector('[data-action="request"]');
    assert.equal(request.textContent, 'c7a1e9d0', 'the request\'s chip shows the start of its id');
    assert.equal(request.getAttribute('href'), '/admin/activitylog/?request=c7a1e9d0-55aa-4c1b-9f00-000000000001');

    const click = fire(request, 'click');
    assert.equal(click.defaultPrevented, true);
    const modified = fire(item.querySelector('[data-action="actor"]'), 'click', {ctrlKey: true});
    assert.equal(modified.defaultPrevented, false);
    fire(item.querySelector('[data-action="actor"]'), 'click');
    assert.deepEqual(plain(t.asked), [
        {key: 'request', value: 'c7a1e9d0-55aa-4c1b-9f00-000000000001', alone: true},
        {key: 'by', value: 'john', alone: true}
    ]);
});

test('timeline: without links or in-place filtering the actor and the request are plain text', async () => {
    const t = mounted({searchLink: undefined, ask: undefined});

    t.timeline.show(SEARCH);
    t.search.calls[0].resolve(resultPage({events: [event()], total: {count: 1, atLeast: false}}));
    await flush();

    const item = t.root.querySelector('[data-activity="1"]');
    assert.equal(item.querySelector('[data-action="actor"]'), null);
    assert.equal(item.querySelector('[data-action="request"]'), null);
    assert.equal(t.slot('correlation', item).textContent, 'c7a1e9d0');
});

test('timeline: "Load more" continues the last day, moves focus to the first new activity, and ends', async () => {
    const t = mounted();

    t.timeline.show(SEARCH);
    t.search.calls[0].resolve(resultPage({
        events: [event({id: 2})],
        total: {count: 2, atLeast: false},
        nextCursor: 'after-2'
    }));
    await flush();
    fire(t.slot('more'), 'click');
    fire(t.slot('more'), 'click');
    assert.equal(t.search.calls.length, 2, 'a page is asked for once, however often the button is pressed');
    assert.equal(t.search.calls[1].query.cursor, 'after-2');
    assert.equal(t.slot('more').disabled, true);

    t.search.calls[1].resolve(resultPage({
        events: [event({id: 1, occurredAtMs: utc(2026, 9, 22, 7)})],
        total: {count: 2, atLeast: false},
        nextCursor: null
    }));
    await flush();

    const sections = t.slot('list').children;
    assert.equal(sections.length, 1, 'the same day goes on');
    assert.equal(t.slot('count', sections[0]).textContent, '2 events');
    assert.equal(t.doc.activeElement, t.root.querySelector('[data-activity="1"]'));
    assert.equal(t.root.querySelector('[data-activity="1"]').getAttribute('tabindex'), '-1');
    assert.equal(t.slot('count').textContent, '2');
    assert.equal(t.slot('paging').hidden, true);
});

test('timeline: only the latest search draws; an older answer or its next page is dropped', async () => {
    const t = mounted();
    const newer = Object.assign({}, SEARCH, {chips: []});

    t.timeline.show(SEARCH);
    t.timeline.show(newer);
    assert.equal(t.search.calls[0].signal.aborted, true, 'the older request is aborted');
    t.search.calls[0].resolve(resultPage({events: [event({id: 7})], total: {count: 1, atLeast: false}}));
    await flush();
    assert.equal(t.root.querySelector('[data-activity="7"]'), null);
    assert.ok(t.root.querySelector('[data-part="skeleton"]'), 'still loading the newer search');

    t.search.calls[1].resolve(resultPage({events: [event({id: 5})], total: {count: 9, atLeast: false},
        nextCursor: 'after-5'}));
    await flush();
    fire(t.slot('more'), 'click');
    t.timeline.show(SEARCH);
    t.search.calls[2].resolve(resultPage({events: [event({id: 4})], total: {count: 9, atLeast: false}}));
    await flush();
    assert.equal(t.root.querySelector('[data-activity="4"]'), null, 'a page of the search before is not appended');
    t.search.calls[2].reject({kind: 'aborted'});
    await flush();
    assert.equal(t.reported.length, 0, 'giving up a search is no failure');
});

test('timeline: no match in the range explains itself and offers the ways out the page gave', async () => {
    const t = mounted();

    t.timeline.show(SEARCH);
    t.search.calls[0].resolve(resultPage({
        outside: {count: 12, atLeast: false},
        latestOutsideMs: utc(2026, 8, 30, 10),
        narrowed: true
    }));
    await flush();

    const state = t.root.querySelector('[data-part="state"]');
    assert.equal(t.slot('title', state).textContent, 'No changes match in Last 7 days');
    assert.equal(t.slot('text', state).textContent,
        '12 matching changes are outside. The latest is 30 Sept 2026, 12:00.');
    assert.equal(t.slot('count').textContent, '0');
    assert.equal(t.slot('notice').hidden, false);
    assert.equal(t.slot('notice').textContent, 'Only the newest 10,000 were searched.');

    const buttons = t.slot('actions', state).children.map((button) => button.getAttribute('data-action'));
    assert.deepEqual(buttons, ['widen', 'remove-last', 'clear-all']);
    t.slot('actions', state).children.forEach((button) => fire(button, 'click'));
    assert.deepEqual(t.actions, ['widen', 'removeLast', 'clearAll']);

    const bare = mounted({actions: {clearAll: () => undefined}});
    bare.timeline.show(SEARCH);
    bare.search.calls[0].resolve(resultPage({outside: {count: 12, atLeast: false}, latestOutsideMs: 1}));
    await flush();
    assert.deepEqual(bare.slot('actions', bare.root.querySelector('[data-part="state"]')).children
        .map((button) => button.getAttribute('data-action')), ['clear-all'], 'a way out is offered only when given');
});

test('timeline: a host may leave out the count line and give its own words for no match', async () => {
    const asked = [];
    const t = mounted({
        countLine: false,
        actions: {},
        noResults: (shown, first) => {
            asked.push({shown, first});

            return {title: 'Nothing here', text: 'Try another choice.'};
        }
    });

    t.timeline.show(SEARCH);
    t.search.calls[0].resolve(resultPage({outside: {count: 2, atLeast: false}, latestOutsideMs: 1}));
    await flush();

    const state = t.root.querySelector('[data-part="state"]');
    assert.equal(t.slot('head').hidden, true, 'no count line');
    assert.equal(t.slot('title', state).textContent, 'Nothing here');
    assert.equal(t.slot('text', state).textContent, 'Try another choice.');
    assert.equal(t.slot('actions', state).children.length, 0, 'no way out the host did not give');
    assert.equal(asked.length, 1);
    assert.equal(asked[0].shown, SEARCH);
    assert.deepEqual(plain(asked[0].first.outside), {count: 2, atLeast: false});

    t.timeline.show(SEARCH);
    t.search.calls[1].resolve(resultPage({events: [event({id: 9})], total: {count: 1, atLeast: false}}));
    await flush();
    assert.equal(t.slot('head').hidden, true, 'nor with results');
    assert.equal(t.slot('list').children.length, 1);
});

test('timeline: an empty log shows the example searches as cards that open them', async () => {
    const t = mounted();

    t.timeline.show(SEARCH);
    t.search.calls[0].resolve(resultPage({journalEmpty: true, total: {count: 0, atLeast: false}}));
    await flush();

    const empty = t.root.querySelector('[data-part="empty-log"]');
    assert.equal(t.slot('title', empty).textContent, 'emptyLogTitle');
    assert.equal(t.slot('head').hidden, true);
    const card = t.slot('examples', empty).children[0];
    assert.equal(card.textContent, 'Prices changedfield:price· Last 30 days');
    fire(t.slot('title', card), 'click');
    assert.deepEqual(t.actions, ['example 0']);
});

test('timeline: a refused or failed search is reported, explained in the server\'s words, and retried', async () => {
    const t = mounted();

    t.timeline.show(SEARCH);
    t.search.calls[0].reject({kind: 'refused', reason: 'A search takes at most 20 filters.'});
    await flush();

    const state = t.root.querySelector('[data-part="state"]');
    assert.equal(t.slot('title', state).textContent, 'loadError');
    assert.equal(t.slot('text', state).textContent, 'A search takes at most 20 filters.');
    assert.equal(t.reported.length, 1);
    fire(t.slot('actions', state).children[0], 'click');
    assert.equal(t.search.calls.length, 2);
    assert.deepEqual(plain(t.search.calls[1].query), plain(t.search.calls[0].query));

    t.search.calls[1].reject({kind: 'failed', reason: null});
    await flush();
    assert.equal(t.slot('text', t.root.querySelector('[data-part="state"]')).textContent, '',
        'a failure\'s details go to the report, not to the page');
});

test('timeline: mounting twice gives the same timeline; destroy stops it and empties the root', async () => {
    const t = mounted();
    const again = load('timeline/timeline').mount(t.root, {report: () => undefined});

    assert.equal(again, t.timeline);
    assert.throws(() => load('timeline/timeline').mount(page().root, {}), /report function/);
    t.timeline.show(SEARCH);
    t.timeline.destroy();
    assert.equal(t.search.calls[0].signal.aborted, true);
    assert.equal(t.root.children.length, 0);
});

/**
 * A browser whose fetch the test answers and whose timers it fires
 */
function fakeBrowser() {
    const fetches = [];
    const timers = [];

    return {
        fetches,
        timers,
        AbortController,
        setTimeout: (callback, ms) => timers.push({callback, ms, cleared: false}) - 1,
        clearTimeout: (id) => {
            timers[id].cleared = true;
        },
        fetch: (url, init) => new Promise((resolve, reject) => {
            fetches.push({url, init, resolve, reject});
            init.signal.addEventListener('abort', () => reject(new Error('aborted')));
        })
    };
}

const response = (status, body) => ({ok: status >= 200 && status < 300, status, json: () => Promise.resolve(body)});

test('search client: a page is asked for by query string on the given address and parsed', async () => {
    const browser = fakeBrowser();
    const client = load('timeline/search-client').create('/admin/activitylog/activity/search/key/abc/', 20000,
        browser);
    const answer = client.page({range: {from: 1, to: 2}, chips: [{key: 'by', value: 'jo hn'}], changesOnly: true,
        cursor: 'c1', limit: 50}, new AbortController().signal);

    assert.equal(browser.fetches[0].url,
        '/admin/activitylog/activity/search/key/abc/?from=1&to=2&c%5B%5D=by%3Ajo+hn&changes=1&cursor=c1&limit=50');
    assert.equal(browser.fetches[0].init.credentials, 'same-origin');
    assert.equal(browser.timers[0].ms, 20000);
    browser.fetches[0].resolve(response(200, resultPage({events: [event()], total: {count: 1, atLeast: false}})));

    const parsed = await answer;
    assert.equal(parsed.events[0].actor.name, 'john');
    assert.equal(browser.timers[0].cleared, true, 'the deadline is released');
});

test('search client: refusals carry the server\'s reason; anything else fails without one', async () => {
    const {SearchFailure, create} = load('timeline/search-client');
    const browser = fakeBrowser();
    const client = create('/search', 1000, browser);
    const query = {range: {from: 1, to: 2}, chips: [], changesOnly: false, cursor: null, limit: 50};
    const ask = () => client.page(query, new AbortController().signal).then(() => null, (failure) => failure);

    let failure = ask();
    browser.fetches[0].resolve(response(400, {error: 'Unknown range.'}));
    failure = await failure;
    assert.ok(failure instanceof SearchFailure);
    assert.deepEqual([failure.kind, failure.reason], ['refused', 'Unknown range.']);

    failure = ask();
    browser.fetches[1].resolve(response(500, null));
    assert.deepEqual([(await failure).kind, (await failure).reason], ['failed', null]);

    failure = ask();
    browser.fetches[2].resolve(response(200, {events: 'none'}));
    assert.equal((await failure).kind, 'failed', 'a body of another shape is not drawn');

    failure = ask();
    browser.timers[3].callback();
    assert.equal((await failure).kind, 'failed', 'the deadline passed');
    assert.match((await failure).cause.message, /timed out/);

    const owner = new AbortController();
    failure = client.page(query, owner.signal).then(() => null, (given) => given);
    owner.abort();
    assert.equal((await failure).kind, 'aborted');

    failure = await client.page({range: {from: 3, to: 2}, chips: [], changesOnly: true}, owner.signal)
        .then(() => null, (given) => given);
    assert.equal(failure.kind, 'failed', 'a malformed search is not sent');
    assert.equal(browser.fetches.length, 5);
});

test('summary: the filters are counted at once; the overall counts follow from the first page of the search', async () => {
    const doc = new FakeDocument();
    const element = doc.createElement('span');
    const search = fakeSearch();
    const reported = [];
    const summary = load('search-page/summary').create(element, {
        words: {filtersOne: '%1 filter', filtersMany: '%1 filters', overallOne: '%1 matching change overall, %2 in range',
            overallMany: '%1 matching changes overall, %2 in range'},
        formatNumber: (value) => value.toLocaleString('en-US'),
        countText: (bounded) => bounded.count.toLocaleString('en-US') + (bounded.atLeast ? '+' : ''),
        search: search,
        report: (failure) => reported.push(failure),
        signal: new AbortController().signal
    });
    const two = Object.assign({}, SEARCH, {chips: [{key: 'by', value: 'john'}, {key: 'field', value: 'price'}]});

    summary.follow(two, 'timeline');
    assert.equal(element.textContent, '2 filters');
    assert.equal(search.calls.length, 0, 'the timeline reads the counts anyway');
    summary.report(SEARCH, resultPage({overall: {count: 5, atLeast: false}, total: {count: 1, atLeast: false}}));
    assert.equal(element.textContent, '2 filters', 'counts of another search are not written');
    summary.report(two, resultPage({overall: {count: 10000, atLeast: true}, total: {count: 8, atLeast: false}}));
    assert.equal(element.textContent, '2 filters · 10,000+ matching changes overall, 8 in range');

    summary.follow(SEARCH, 'grid');
    assert.equal(search.calls[0].query.limit, 1, 'the grid view asks for the counts alone');
    summary.follow(two, 'grid');
    assert.equal(search.calls[0].signal.aborted, true);
    search.calls[1].resolve(resultPage({overall: {count: 1, atLeast: false}, total: {count: 1, atLeast: false}}));
    await flush();
    assert.equal(element.textContent, '2 filters · 1 matching change overall, 1 in range');

    summary.follow(Object.assign({}, SEARCH, {chips: []}), 'grid');
    assert.equal(element.textContent, '');
    assert.equal(search.calls.length, 2, 'a search without filters has nothing to sum up');
    assert.equal(reported.length, 0);
});

test('summary: a request for the counts lets go of the page\'s signal once it settled', async () => {
    const doc = new FakeDocument();
    const listening = new Set();
    const pageSignal = {
        aborted: false,
        addEventListener: (type, handler) => listening.add(handler),
        removeEventListener: (type, handler) => listening.delete(handler)
    };
    const search = fakeSearch();
    const summary = load('search-page/summary').create(doc.createElement('span'), {
        words: {filtersOne: '%1 filter', filtersMany: '%1 filters', overallOne: '%1 overall, %2 in range',
            overallMany: '%1 overall, %2 in range'},
        formatNumber: String,
        countText: (bounded) => String(bounded.count),
        search: search,
        report: () => {},
        signal: pageSignal
    });

    summary.follow(SEARCH, 'grid');
    summary.follow(Object.assign({}, SEARCH, {changesOnly: false}), 'grid');
    assert.equal(listening.size, 2, 'each request in flight ends with the page');
    search.calls[0].reject({kind: 'aborted'});
    search.calls[1].resolve(resultPage({overall: {count: 1, atLeast: false}, total: {count: 1, atLeast: false}}));
    await flush();
    assert.equal(listening.size, 0, 'settled requests leave no listener behind');
});
