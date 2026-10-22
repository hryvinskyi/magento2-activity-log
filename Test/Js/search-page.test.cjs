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

const utc = (...parts) => Date.UTC(...parts);
const copenhagen = () => load('search/zone').create('Europe/Copenhagen', 'en_GB');
const NOW = utc(2026, 9, 22, 12, 30);

const KEYS = [
    {key: 'product', label: 'Product', hint: 'SKU, ID or name', group: 'records', matchesFieldNames: false},
    {key: 'config', label: 'Setting', hint: 'Config path', group: 'fields', matchesFieldNames: true},
    {key: 'by', label: 'Actor', hint: 'Name', group: 'people', matchesFieldNames: false},
    {key: 'channel', label: 'Channel', hint: 'Where', group: 'other', matchesFieldNames: false},
    {key: 'request', label: 'Request', hint: 'Correlation ID', group: 'other', matchesFieldNames: false},
    {key: 'outcome', label: 'Outcome', hint: 'Success', group: 'other', matchesFieldNames: false},
    {key: 'text', label: 'Text', hint: 'Words', group: 'other', matchesFieldNames: false}
];
const KEY_CODES = KEYS.map((description) => description.key);
const CHANNELS = {
    admin: {label: 'Admin request', bg: '#fff', fg: '#000'},
    entity: {label: 'Entity change', bg: '#fff', fg: '#000'}
};

const WORDS = {
    placeholder: 'Search changes', placeholderMore: 'Add another filter…', removeChip: 'Remove filter %1',
    textKey: 'text', quoted: '“%1”', anywhere: 'anywhere in the activity', unknownKey: 'unknown filter',
    filterBy: 'Filter by', matching: 'Matching %1', textGroup: 'Text', typed: 'Use as typed',
    limit: 'At most %1 filters.', tooLong: 'At most %1 characters.', pickDates: 'pick dates and times',
    calendarFrom: 'Click a start day', calendarTo: 'Now click the end day.', fromDate: 'Enter the start date.',
    fromTime: 'Enter the start time.', toDate: 'Enter the end date.', toTime: 'Enter the end time.',
    filtersOne: '%1 filter', filtersMany: '%1 filters', overallOne: '%1 overall, %2 in range',
    overallMany: '%1 overall, %2 in range',
    presets: {hour: 'Last hour', today: 'Today', yesterday: 'Yesterday', '7d': 'Last 7 days', '30d': 'Last 30 days',
        custom: 'Custom'},
    groups: {fields: 'Fields', records: 'Records', people: 'People & clients', types: 'Entity types', other: 'Other'}
};

const opening = (search, preferences = {view: 'timeline', changesOnly: true}, changeChannels = ['entity']) =>
    load('search/search-state').initial({
        search: search,
        keys: KEY_CODES,
        nowMs: NOW,
        zone: copenhagen(),
        preferences: preferences,
        changeChannels: changeChannels,
        channels: CHANNELS
    });

test('page state: a link opens on its chips and range; the menu links open on what they name', () => {
    const plainLink = opening('');
    assert.deepEqual(plain(plainLink.chips), []);
    assert.equal(plainLink.range.preset, '7d', 'the default range is the last 7 days');
    assert.equal(plainLink.changesOnly, true);
    assert.equal(plainLink.view, 'timeline');

    const admin = opening('?channel=admin');
    assert.deepEqual(plain(admin.chips), [{key: 'channel', value: 'admin'}]);
    assert.equal(admin.changesOnly, false, 'admin requests carry no diff, so "Changes only" would hide all of them');

    const entity = opening('?channel=entity');
    assert.equal(entity.changesOnly, true, 'a channel that records changes keeps the admin\'s choice');
    assert.equal(opening('?channel=Entity%20change').changesOnly, true, 'a channel is known by its label too');

    const request = opening('?correlation_id=c7a1e9&range=30d');
    assert.deepEqual(plain(request.chips), [{key: 'request', value: 'c7a1e9'}], 'the old link parameter still works');
    assert.equal(request.range.preset, '30d');
    assert.equal(request.changesOnly, false, 'every activity of a request is shown, as following it in the timeline');

    assert.deepEqual(plain(opening('?actor_name=jane').chips), [{key: 'by', value: 'jane'}]);
    assert.equal(opening('?actor_name=jane').changesOnly, true);

    const off = opening('', {view: 'grid', changesOnly: false});
    assert.equal(off.changesOnly, false);
    assert.equal(off.view, 'grid');
    assert.equal(opening('', {view: 'board', changesOnly: true}).view, 'timeline', 'an unknown view falls back');
    assert.equal(opening('', undefined, []).changesOnly, false, 'without a change channel the switch is off');

    const broken = opening('?range=never&by=jane');
    assert.equal(broken.range.preset, '7d', 'an unreadable range opens on the default one');
    assert.equal(broken.dropped, 1);
});

test('page state: requests take the range up to now; the grid and the link get the search in their own form', () => {
    const state = load('search/search-state');
    const zone = copenhagen();
    const opened = opening('?by=jane&config=carriers/freeshipping/active&range=hour');
    const later = state.snapshot(opened, NOW + 3600000, zone);

    assert.deepEqual(plain(later.range), {preset: 'hour', from: NOW, to: NOW + 3600000},
        'a page left open an hour asks for the hour that ends now');
    assert.ok(Object.isFrozen(later) && Object.isFrozen(later.chips));
    assert.deepEqual(plain(state.gridParam(later)), {
        from: String(NOW),
        to: String(NOW + 3600000),
        c: ['by:jane', 'config:carriers/freeshipping/active'],
        changes: '1'
    });
    assert.equal(state.linkQuery(opened), 'by=jane&config=carriers%2Ffreeshipping%2Factive&range=hour');
    assert.equal(state.linkQuery(opening('?by=jane')), 'by=jane', 'the default range is left out of the link');

    const custom = state.update(opened, {range: {preset: 'custom', from: 1000, to: 2000}});
    assert.deepEqual(plain(state.snapshot(custom, NOW, zone).range), {preset: 'custom', from: 1000, to: 2000},
        'a typed range stays as typed');
    assert.equal(state.linkQuery(custom), 'by=jane&config=carriers%2Ffreeshipping%2Factive&range=1000-2000');
});

test('page state: a filter another part of the page asks for obeys the grammar and may switch changes-only off', () => {
    const state = load('search/search-state');
    const context = {keys: KEY_CODES, changeChannels: ['entity'], channels: CHANNELS};
    const opened = opening('?by=jane');

    const byRequest = state.withCriterion(opened, {key: 'request', value: 'c7a1e9'}, context);
    assert.ok(byRequest.ok);
    assert.deepEqual(plain(byRequest.state.chips), [{key: 'by', value: 'jane'}, {key: 'request', value: 'c7a1e9'}]);
    assert.equal(byRequest.state.changesOnly, false);
    assert.equal(opened.changesOnly, true, 'the state is copied, never changed');

    const byActor = state.withCriterion(opened, {key: 'by', value: 'ERP sync'}, context);
    assert.equal(byActor.state.changesOnly, true);

    const unknown = state.withCriterion(opened, {key: 'colour', value: 'red'}, context);
    assert.deepEqual(plain(unknown.state.chips[1]), {key: 'text', value: 'colour: red'},
        'a key the page does not know is searched as text');

    let full = opened;
    for (let i = 0; i < 19; i += 1) {
        full = state.withCriterion(full, {key: 'by', value: 'actor ' + i}, context).state;
    }
    const refused = state.withCriterion(full, {key: 'by', value: 'one too many'}, context);
    assert.deepEqual([refused.ok, refused.error, refused.state.chips.length], [false, 'limit', 20]);
});

test('page state: an example search sets its filters and its range at this moment', () => {
    const state = load('search/search-state');
    const zone = copenhagen();
    const example = {chips: [{key: 'config', value: 'carriers'}, {key: 'by', value: 'jane'}], preset: '30d'};

    const picked = state.withExample(opening('?outcome=failure'), example, KEY_CODES, NOW, zone);
    assert.ok(picked.ok);
    assert.deepEqual(plain(picked.state.chips), example.chips, 'the example replaces the chips');
    assert.equal(picked.state.range.preset, '30d');
    assert.equal(picked.state.range.to, NOW);

    const long = 'x'.repeat(256);
    const bad = state.withExample(opening(''), {chips: [{key: 'by', value: long}], preset: '30d'}, KEY_CODES, NOW, zone);
    assert.deepEqual([bad.ok, bad.error], [false, 'tooLong']);
    const badRange = state.withExample(opening(''), {chips: [], preset: 'forever'}, KEY_CODES, NOW, zone);
    assert.deepEqual([badRange.ok, badRange.error], [false, 'unknownPreset']);
});

test('combobox: arrows move within the offers, Enter takes the active one or the typed text', () => {
    const combobox = load('search/combobox');
    let state = combobox.initial();

    assert.equal(combobox.keyDown('Enter', state, 0, false), null, 'Enter on nothing does nothing');
    assert.equal(combobox.keyDown('a', state, 3, false), null, 'letters are the input\'s own');

    state = combobox.keyDown('ArrowDown', state, 3, false).state;
    assert.deepEqual(plain(state), {input: '', open: true, active: 0}, 'the first arrow opens on the first offer');
    state = combobox.keyDown('ArrowDown', state, 3, false).state;
    state = combobox.keyDown('ArrowDown', state, 3, false).state;
    state = combobox.keyDown('ArrowDown', state, 3, false).state;
    assert.equal(state.active, 2, 'the last offer is the end');
    state = combobox.keyDown('ArrowUp', state, 3, false).state;
    assert.equal(state.active, 1);
    assert.equal(combobox.keyDown('Enter', state, 3, false).type, 'choose');

    const typed = combobox.typed('by: jane');
    assert.deepEqual(plain(typed), {input: 'by: jane', open: true, active: 0}, 'typing opens on the best offer');
    assert.equal(combobox.keyDown('Enter', typed, 0, false).type, 'commit', 'with no offers the text is used');
    assert.equal(combobox.keyDown('Enter', combobox.closed(typed), 4, false).type, 'commit');

    const closing = combobox.keyDown('Escape', typed, 4, true);
    assert.deepEqual([closing.type, closing.state.open, closing.state.input], ['close', false, 'by: jane']);
    assert.equal(combobox.keyDown('Escape', closing.state, 4, true), null);

    assert.equal(combobox.keyDown('Backspace', combobox.initial(), 0, true).type, 'removeLast');
    assert.equal(combobox.keyDown('Backspace', combobox.initial(), 0, false), null, 'no chip to remove');
    assert.equal(combobox.keyDown('Backspace', typed, 0, true), null, 'Backspace edits the text first');

    assert.equal(combobox.hovered(typed, 3, 4).active, 3);
    assert.equal(combobox.hovered(typed, 9, 4).active, 0, 'an offer that is not shown is not made active');
    assert.deepEqual(plain(combobox.cleared()), {input: '', open: false, active: -1});
});

test('range draft: presets fill the inputs, typing and picking days make it custom, apply gives the range', () => {
    const draft = load('search/range-draft');
    const zone = copenhagen();
    const range = load('search/range');
    const week = range.preset('7d', NOW, zone).range;

    let current = draft.open(week, zone);
    assert.deepEqual(plain(current), {
        fromDate: '2026-10-16', fromTime: '00:00', toDate: '2026-10-22', toTime: '14:30',
        year: 2026, month: 10, preset: '7d', picking: 'from'
    });

    current = draft.choosePreset(current, 'yesterday', NOW, zone);
    assert.deepEqual([current.preset, current.fromDate, current.toDate, current.toTime],
        ['yesterday', '2026-10-21', '2026-10-21', '23:59']);
    assert.deepEqual(plain(draft.apply(current, NOW, zone).range), plain(range.preset('yesterday', NOW, zone).range));

    current = draft.pickDay(current, '2026-10-05', '2026-10-22');
    assert.deepEqual([current.preset, current.fromDate, current.toDate, current.picking],
        ['custom', '2026-10-05', '2026-10-21', 'to']);
    current = draft.pickDay(current, '2026-10-10', '2026-10-22');
    assert.deepEqual([current.fromDate, current.toDate, current.picking], ['2026-10-05', '2026-10-10', 'from']);
    assert.equal(draft.pickDay(current, '2026-10-30', '2026-10-22'), current, 'a day to come cannot be picked');

    current = draft.type(current, 'fromTime', '08:15');
    const applied = draft.apply(current, NOW, zone);
    assert.deepEqual(plain(applied.range), {
        preset: 'custom',
        from: utc(2026, 9, 5, 6, 15),
        to: utc(2026, 9, 10, 21, 59) + 59999
    }, 'the end minute is included to its last millisecond, in the store\'s timezone');
    assert.equal(draft.type(current, 'colour', 'red'), current);
    assert.deepEqual(plain(draft.apply(draft.type(current, 'toDate', '2026-13-40'), NOW, zone)),
        {ok: false, range: null, error: 'toDate'});

    current = draft.shiftMonth(current, -1);
    assert.deepEqual([current.year, current.month], [2026, 9]);
    assert.equal(draft.choosePreset(current, 'custom', NOW, zone).preset, 'custom');
});

test('range draft: the popover lists each preset with its current range and the calendar of the month', () => {
    const draft = load('search/range-draft');
    const zone = copenhagen();
    const view = draft.view(draft.open(load('search/range').preset('7d', NOW, zone).range, zone), NOW, zone, WORDS);

    assert.deepEqual(plain(view.presets.map((preset) => [preset.code, preset.label, preset.active])), [
        ['hour', 'Last hour', false], ['today', 'Today', false], ['yesterday', 'Yesterday', false],
        ['7d', 'Last 7 days', true], ['30d', 'Last 30 days', false], ['custom', 'Custom', false]
    ]);
    assert.equal(view.presets[5].detail, 'pick dates and times');
    assert.match(view.presets[0].detail, /22 Oct.*13:30.*14:30/);
    assert.equal(view.monthLabel, 'October 2026');
    assert.equal(view.weekdays.length, 7);
    assert.equal(view.days.length, 42);
    assert.equal(view.canGoNext, false, 'there is nothing to see after this month');
    assert.equal(view.hint, 'Click a start day');
    assert.equal(view.days.filter((day) => day.isFrom || day.isTo).length, 2);
    assert.match(view.days.find((day) => day.key === '2026-10-22').label, /22 Oct 2026/);
    assert.equal(draft.dayKey(utc(2026, 9, 21, 22, 30), zone), '2026-10-22', 'today is the store\'s today');
});

test('page config: the printed config is checked before the page uses it', () => {
    const pageConfig = load('search/page-config');
    const good = {
        searchUrl: '/admin/activitylog/activity/search/key/abc/',
        suggestUrl: 'https://shop.test/admin/activitylog/activity/suggest/key/abc/',
        preferenceUrl: '/admin/activitylog/activity/savePreference/key/abc/',
        formKey: 'fk', timeZone: 'Europe/Copenhagen', locale: 'en_GB', serverNowMs: NOW, timeoutMs: 20000,
        keys: KEYS, channels: CHANNELS, changeChannels: ['entity'],
        preferences: {view: 'grid', changesOnly: false},
        examples: [{title: 'Prices', chips: [{key: 'field', value: 'price'}], preset: '30d'}],
        grid: {provider: 'p', paging: 'g', selections: 's', exportButton: 'e', exportOption: 'csv',
            param: 'activity_search'},
        wording: WORDS
    };

    const parsed = pageConfig.parse(good);
    assert.equal(parsed.searchUrl, good.searchUrl);
    assert.deepEqual(plain(parsed.keys.map((description) => description.key)), KEY_CODES);
    assert.deepEqual(plain(parsed.preferences), {view: 'grid', changesOnly: false});
    assert.equal(parsed.wording.presets['30d'], 'Last 30 days');

    const broken = (changes) => () => pageConfig.parse(Object.assign({}, good, changes));
    assert.throws(broken({searchUrl: 'javascript:alert(1)'}), {name: 'PageConfigError'});
    assert.throws(broken({preferences: {view: 'board', changesOnly: true}}), {name: 'PageConfigError'});
    assert.throws(broken({serverNowMs: '1'}), {name: 'PageConfigError'});
    assert.throws(broken({wording: Object.assign({}, WORDS, {limit: undefined})}), {name: 'PageConfigError'});
    assert.throws(broken({examples: [{title: 'x', chips: 'by: x', preset: '7d'}]}), {name: 'PageConfigError'});
    assert.throws(broken({keys: [{key: 'Bad Key'}]}), {name: 'SearchShapeError'});
    assert.throws(() => pageConfig.parse(null), {name: 'PageConfigError'});
    assert.throws(broken({grid: Object.assign({}, good.grid, {selections: undefined})}), {name: 'PageConfigError'},
        'the grid\'s selection is named, for a new search to clear it');
});

test('search event: the results area is told the search and the view, and can check what it is told', () => {
    const searchEvent = load('search/search-event');
    const snapshot = load('search/search-state').snapshot(opening('?by=jane'), NOW, copenhagen());
    const detail = searchEvent.detail(snapshot, 'grid');

    assert.equal(searchEvent.NAME, 'activity-search:search');
    assert.ok(Object.isFrozen(detail));
    assert.equal(searchEvent.read(detail), detail);
    assert.equal(searchEvent.read({chips: [], range: {from: 1, to: 2}, changesOnly: true, view: 'board'}), null);
    assert.equal(searchEvent.read(null), null);
});

test('wording: placeholders are filled in order and plurals chosen by count', () => {
    const wording = load('search/wording');

    assert.equal(wording.fill('%1 of %2', 50, '1,204'), '50 of 1,204');
    assert.equal(wording.fill('%2 then %1', 'a', 'b'), 'b then a');
    assert.equal(wording.fill('%1 and %3', 'x'), 'x and %3', 'a placeholder with no value stays');
    assert.equal(wording.fill('%1', '%2'), '%2', 'a value is put in as text, never read as a placeholder');
    assert.equal(wording.count(1, '%1 filter', '%1 filters', String), '1 filter');
    assert.equal(wording.count(1200, '%1 filter', '%1 filters', (n) => n.toLocaleString('en-US')), '1,200 filters');
});

test('offer view: groups get their titles, values their counts, setting paths a monospaced font', () => {
    const offerView = load('search/offer-view');
    const suggest = load('search/suggest');
    const vocabulary = {
        keys: KEYS,
        lists: {
            by: [{value: 'jane', label: 'jane', hint: 'Admin', count: 1204}],
            config: [{value: 'carriers/freeshipping/active', label: 'Free Shipping › Enabled', hint: '', count: 3}]
        }
    };
    const format = (n) => n.toLocaleString('en-US');

    const read = plain(offerView.offers(suggest.suggest('a', [], vocabulary), WORDS, KEYS, format));
    assert.deepEqual(read.map((group) => group.title), ['Filter by', 'Fields', 'People & clients', 'Text']);
    const people = read[2].items[0];
    assert.deepEqual(people, {keyLabel: 'by:', text: 'jane', hint: 'Admin · 1,204', mono: false, index: 2});
    assert.equal(read[1].items[0].mono, true);
    assert.equal(read[1].items[0].hint, '3', 'a value without a hint shows its count alone');
    assert.deepEqual(read[3].items[0], {keyLabel: 'text', text: '“a”', hint: 'anywhere in the activity', mono: false,
        index: 3});

    const typed = plain(offerView.offers(suggest.suggest('by: ja', [], vocabulary), WORDS, KEYS, format));
    assert.deepEqual(typed.map((group) => group.title), ['Matching by', 'Use as typed']);
    assert.equal(typed[1].items[0].text, 'by: ja');

    const unknown = plain(offerView.offers(suggest.suggest('colour: red', [], vocabulary), WORDS, KEYS, format));
    assert.equal(unknown[0].items[0].hint, 'unknown filter');

    assert.deepEqual(plain(offerView.chips([{key: 'config', value: 'a/b'}, {key: 'text', value: 'oops'}], WORDS, KEYS)),
        [
            {keyLabel: 'config:', value: 'a/b', mono: true, plain: false, removeLabel: 'Remove filter config: a/b'},
            {keyLabel: '', value: 'oops', mono: false, plain: true, removeLabel: 'Remove filter text: oops'}
        ]);
});

test('grid link: the grid shows a search from its first page with the page\'s deadline, and its export runs for a search', () => {
    const gridLink = load('search-page/grid-link');
    const calls = [];
    const components = {
        provider: {set: (path, value) => calls.push(['provider', path, value])},
        paging: {goFirst: () => calls.push(['paging', 'goFirst'])},
        selections: {deselectAll: () => calls.push(['selections', 'deselectAll'])},
        exporter: {
            set: (path, value) => calls.push(['export', path, value]),
            checked: (option) => calls.push(['export', 'checked', option]),
            applyOption: () => calls.push(['export', 'run'])
        }
    };
    const registry = {get: (name, callback) => callback(components[name])};
    const link = gridLink.create(registry, {
        provider: 'provider', paging: 'paging', selections: 'selections', exportButton: 'exporter',
        exportOption: 'csv', param: 'activity_search'
    }, 20000);
    const param = {from: '1', to: '2', c: ['by:jane'], changes: '1'};

    link.clearSelection();
    link.show(param);
    link.exportCsv(param);
    assert.deepEqual(plain(calls), [
        ['provider', 'requestConfig', {timeout: 20000}],
        ['selections', 'deselectAll'],
        ['paging', 'goFirst'],
        ['provider', 'params.activity_search', param],
        ['export', 'additionalParams', {activity_search: param}],
        ['export', 'checked', 'csv'],
        ['export', 'run']
    ]);
});

/**
 * The search bar's markup, as the page prints it
 *
 * @returns {{doc: FakeDocument, root: Object, input: Object, part: function(string): Object}}
 */
function barMarkup() {
    const doc = new FakeDocument();
    const el = builder(doc);
    const root = el('div', {'data-role': 'bar'}, [
        el('div', {'data-role': 'field'}, [
            el('span', {'data-role': 'chips'}),
            el('input', {'data-role': 'input', role: 'combobox', 'aria-expanded': 'false'})
        ]),
        el('button', {'data-role': 'clear', hidden: true}),
        el('div', {'data-role': 'popup', hidden: true}, [el('div', {'data-role': 'listbox', role: 'listbox'})]),
        el('div', {'data-role': 'message', hidden: true}),
        el('template', {'data-role': 'icon-remove'}, [el('svg')])
    ]);

    doc.body.appendChild(root);

    return {doc, root, input: root.querySelector('[data-role="input"]'),
        part: (role) => root.querySelector('[data-role="' + role + '"]')};
}

/**
 * @param {Object} markup
 * @returns {{bar: Object, asked: Array<Array<Object>>, lifetime: AbortController}}
 */
function mountBar(markup) {
    const asked = [];
    const lifetime = new AbortController();
    const bar = load('search-page/chip-bar').create(markup.root, {
        words: WORDS,
        keys: KEYS,
        idPrefix: 'search',
        formatNumber: String,
        onChange: (chips) => {
            asked.push(plain(chips));
            bar.render(chips);
        },
        signal: lifetime.signal
    });

    bar.render([]);
    bar.setVocabulary({keys: KEYS, lists: {by: [{value: 'jane', label: 'jane', hint: 'Admin', count: 3}]}});

    return {bar, asked, lifetime};
}

/**
 * @param {Object} markup
 * @param {string} text
 */
function type(markup, text) {
    markup.input.value = text;
    fire(markup.input, 'input');
}

test('search bar: an ARIA combobox whose offers and typed text become chips through the grammar', () => {
    const markup = barMarkup();
    const {asked} = mountBar(markup);

    markup.input.focus();
    assert.equal(markup.input.getAttribute('aria-expanded'), 'true', 'focus shows the keys to filter by');
    assert.equal(markup.input.getAttribute('aria-activedescendant'), 'search-option-0');
    assert.equal(markup.part('listbox').querySelectorAll('[role="option"]').length, KEYS.length - 1);

    type(markup, 'by: ja');
    const options = markup.part('listbox').querySelectorAll('[role="option"]');
    assert.deepEqual(options.map((option) => option.textContent), ['by:janeAdmin · 3', 'by:by: jaName']);
    assert.equal(options[0].getAttribute('aria-selected'), 'true');

    const enter = fire(markup.input, 'keydown', {key: 'Enter'});
    assert.ok(enter.defaultPrevented);
    assert.deepEqual(asked, [[{key: 'by', value: 'jane'}]], 'Enter takes the active offer');
    assert.equal(markup.input.value, '');
    assert.equal(markup.part('chips').children.length, 1);
    assert.equal(markup.part('chips').children[0].querySelector('button').getAttribute('aria-label'),
        'Remove filter by: jane');
    assert.equal(markup.input.placeholder, 'Add another filter…');

    type(markup, 'pro');
    fire(markup.input, 'keydown', {key: 'Enter'});
    assert.equal(markup.input.value, 'product: ', 'choosing a key continues the typing');
    assert.equal(asked.length, 1);

    type(markup, 'colour: red');
    fire(markup.input, 'keydown', {key: 'Escape'});
    assert.equal(markup.part('popup').hidden, true);
    fire(markup.input, 'keydown', {key: 'Enter'});
    assert.deepEqual(asked[1], [{key: 'by', value: 'jane'}, {key: 'text', value: 'colour: red'}],
        'with the list closed Enter takes the text as typed; an unknown key is searched as text');

    fire(markup.input, 'keydown', {key: 'Backspace'});
    assert.deepEqual(asked[2], [{key: 'by', value: 'jane'}], 'Backspace on an empty input removes the last chip');

    fire(markup.part('chips').children[0].querySelector('button'), 'click');
    assert.deepEqual(asked[3], [], 'a chip\'s button removes it');
});

test('search bar: refused chips are explained, clicked offers are taken, and nothing listens after unmount', () => {
    const markup = barMarkup();
    const {bar, asked, lifetime} = mountBar(markup);
    const many = Array.from({length: 20}, (unused, i) => ({key: 'by', value: 'actor ' + i}));

    bar.render(many);
    type(markup, 'one more');
    fire(markup.input, 'keydown', {key: 'Escape'});
    fire(markup.input, 'keydown', {key: 'Enter'});
    assert.equal(asked.length, 0);
    assert.equal(markup.part('message').hidden, false);
    assert.equal(markup.part('message').textContent, 'At most 20 filters.');
    type(markup, 'one');
    assert.equal(markup.part('message').hidden, true, 'typing again clears the message');

    bar.render([]);
    type(markup, 'ja');
    const jane = markup.part('listbox').querySelectorAll('[role="option"]')
        .find((option) => option.textContent.startsWith('by:jane'));
    fire(jane, 'click');
    assert.deepEqual(asked, [[{key: 'by', value: 'jane'}]]);

    fire(markup.part('clear'), 'click');
    assert.deepEqual(asked[1], []);

    lifetime.abort();
    type(markup, 'by: x');
    fire(markup.input, 'keydown', {key: 'Enter'});
    assert.equal(asked.length, 2, 'the bar is quiet once the page is gone');
});

/** The panel's controls as the design lists them: a kind of record with an id box beside it, then single keys */
const PANEL = [
    {key: 'entity', select: true, companion: true, options: [
        {value: 'product', target: 'product'}, {value: 'config_value', target: 'config'},
        {value: 'cart_price_rule', target: 'rule'}, {value: 'catalog_price_rule', target: 'rule'},
        {value: 'admin_user', target: ''}
    ]},
    {key: 'field', select: false, companion: false, options: []},
    {key: 'by', select: false, companion: false, options: []},
    {key: 'channel', select: true, companion: false, options: [{value: 'admin', target: ''}]}
];
const panelValues = (entity, id, rest = {}) => ({
    fields: Object.assign({entity: entity, field: '', by: '', channel: ''}, rest),
    companions: {entity: id}
});

test('panel model: an id typed beside a kind of record becomes a chip of that kind\'s key', () => {
    const panel = load('search/panel-model');
    const kept = [{key: 'text', value: 'sale'}, {key: 'outcome', value: 'denied'}];

    assert.deepEqual(plain(panel.apply(kept, panelValues('product', ' 24-MB01 '), PANEL).chips),
        kept.concat([{key: 'product', value: '24-MB01'}]), 'the other chips stay, the panel\'s follow');
    assert.deepEqual(plain(panel.apply([], panelValues('config_value', 'carriers/flatrate'), PANEL).chips),
        [{key: 'config', value: 'carriers/flatrate'}]);
    assert.deepEqual(plain(panel.apply([], panelValues('admin_user', '7'), PANEL).chips),
        [{key: 'text', value: '7'}], 'a kind that names no key searches the id as text');
    assert.deepEqual(plain(panel.apply([], panelValues('', '1245'), PANEL).chips),
        [{key: 'text', value: '1245'}], 'an id with no kind is searched as text');
    assert.deepEqual(plain(panel.apply([], panelValues('cart_price_rule', ''), PANEL).chips),
        [{key: 'entity', value: 'cart_price_rule'}], 'a kind alone filters by the kind');
    assert.deepEqual(plain(panel.apply([], panelValues('', '', {field: 'price', by: 'jane', channel: 'admin'}), PANEL)
        .chips), [{key: 'field', value: 'price'}, {key: 'by', value: 'jane'}, {key: 'channel', value: 'admin'}]);

    const replaced = panel.apply(
        [{key: 'product', value: 'old'}, {key: 'entity', value: 'product'}, {key: 'rule', value: 'x'},
            {key: 'by', value: 'joe'}],
        panelValues('', '', {by: 'jane'}),
        PANEL
    );
    assert.deepEqual(plain(replaced.chips), [{key: 'by', value: 'jane'}],
        'the panel replaces the chips of every key it holds, its kinds\' keys included');

    assert.equal(panel.apply([], panelValues('product', 'x'.repeat(256)), PANEL).error, 'tooLong');
    const full = Array.from({length: 20}, (unused, index) => ({key: 'text', value: 'w' + index}));
    const refused = panel.apply(full, panelValues('', '', {by: 'jane'}), PANEL);
    assert.deepEqual([refused.ok, refused.error, refused.chips.length], [false, 'limit', 20]);
});

test('panel model: only the controls the admin changed replace chips; two chips of one key stay', () => {
    const panel = load('search/panel-model');
    const chips = [{key: 'by', value: 'jane'}, {key: 'text', value: 'sale'}, {key: 'by', value: 'joe'},
        {key: 'product', value: '24-MB01'}];
    const shown = plain(panel.fill(chips, PANEL));

    assert.deepEqual(plain(panel.apply(chips, shown, PANEL).chips), chips, 'applying the panel untouched keeps every chip');
    assert.deepEqual(
        plain(panel.apply(chips, {fields: Object.assign({}, shown.fields, {channel: 'admin'}),
            companions: shown.companions}, PANEL).chips),
        chips.concat([{key: 'channel', value: 'admin'}]),
        'a channel chosen joins the chips; both actors stay'
    );
    assert.deepEqual(
        plain(panel.apply(chips, {fields: Object.assign({}, shown.fields, {by: ' ann '}),
            companions: shown.companions}, PANEL).chips),
        [{key: 'text', value: 'sale'}, {key: 'product', value: '24-MB01'}, {key: 'by', value: 'ann'}],
        'an actor typed over the one shown replaces the actor chips'
    );
    assert.deepEqual(
        plain(panel.apply(chips, {fields: shown.fields, companions: {entity: '24-MB02'}}, PANEL).chips),
        [{key: 'by', value: 'jane'}, {key: 'text', value: 'sale'}, {key: 'by', value: 'joe'},
            {key: 'product', value: '24-MB02'}],
        'a changed id replaces the record chip only'
    );
});

test('panel model: the chips are shown in the controls, an id under its kind', () => {
    const panel = load('search/panel-model');

    assert.deepEqual(plain(panel.fill([{key: 'text', value: 'sale'}, {key: 'rule', value: 'Summer'},
        {key: 'by', value: 'jane'}, {key: 'by', value: 'joe'}], PANEL)), {
        fields: {entity: 'cart_price_rule', field: '', by: 'jane', channel: ''},
        companions: {entity: 'Summer'}
    }, 'a target key shows the first kind naming it; a key shows its first chip; plain words stay in the bar');
    assert.deepEqual(plain(panel.fill([{key: 'entity', value: 'admin_user'}], PANEL)).fields.entity, 'admin_user');
    assert.deepEqual(plain(panel.fill([], PANEL)), {
        fields: {entity: '', field: '', by: '', channel: ''}, companions: {entity: ''}
    });
});

test('filters panel: shows the chips, keeps values a select does not list, applies and closes', () => {
    const doc = new FakeDocument();
    const el = builder(doc);
    const toggle = el('button', {'aria-expanded': 'false'});
    const panel = el('div', {hidden: true}, [
        el('select', {'data-filter-key': 'entity'}, [
            el('option', {value: ''}),
            el('option', {value: 'product', 'data-target': 'product'}),
            el('option', {value: 'admin_user'})
        ]),
        el('input', {'data-companion-of': 'entity'}),
        el('select', {'data-filter-key': 'channel'}, [el('option', {value: ''}), el('option', {value: 'admin'})]),
        el('input', {'data-filter-key': 'by'}),
        el('span', {'data-role': 'panel-error', hidden: true}),
        el('button', {'data-role': 'panel-cancel'}),
        el('button', {'data-role': 'panel-apply'})
    ]);
    const applied = [];
    doc.body.appendChild(toggle);
    doc.body.appendChild(panel);
    const filters = load('search-page/filters-panel').create(toggle, panel, {
        words: WORDS,
        onApply: (chips) => {
            applied.push(plain(chips));
            filters.sync(chips);
        },
        signal: new AbortController().signal
    });
    const entity = panel.querySelector('[data-filter-key="entity"]');
    const id = panel.querySelector('[data-companion-of="entity"]');
    const select = panel.querySelector('[data-filter-key="channel"]');
    const by = panel.querySelector('[data-filter-key="by"]');

    filters.sync([{key: 'by', value: 'jane'}, {key: 'channel', value: 'Admin request'}, {key: 'product', value: '1245'}]);
    fire(toggle, 'click');
    assert.equal(panel.hidden, false);
    assert.equal(toggle.getAttribute('aria-expanded'), 'true');
    assert.deepEqual([entity.value, id.value, by.value], ['product', '1245', 'jane'], 'the panel shows the chips');
    assert.equal(select.value, 'Admin request');
    assert.equal(select.options.length, 3, 'a chip value the select does not list is added, so applying keeps it');

    filters.sync([{key: 'channel', value: 'admin'}]);
    assert.deepEqual([entity.value, id.value, by.value, select.value, select.options.length], ['', '', '', 'admin', 2],
        'the panel follows the bar');

    by.value = 'x'.repeat(300);
    fire(panel.querySelector('[data-role="panel-apply"]'), 'click');
    assert.equal(panel.hidden, false, 'refused values keep the panel open');
    assert.equal(panel.querySelector('[data-role="panel-error"]').textContent, 'At most 255 characters.');
    assert.deepEqual(applied, []);

    by.value = 'erp';
    entity.value = 'product';
    id.value = '24-MB01';
    fire(panel.querySelector('[data-role="panel-apply"]'), 'click');
    assert.deepEqual(applied[0], [{key: 'channel', value: 'admin'}, {key: 'product', value: '24-MB01'},
        {key: 'by', value: 'erp'}], 'the chip of the control left alone stays; the changed controls\' follow');
    assert.equal(panel.hidden, true);
    assert.equal(doc.activeElement, toggle, 'after Apply the focus goes back to the Filters button');

    fire(toggle, 'click');
    id.value = '';
    by.value = 'ann';
    fire(by, 'keydown', {key: 'Enter'});
    assert.deepEqual(applied[1], [{key: 'channel', value: 'admin'}, {key: 'entity', value: 'product'},
        {key: 'by', value: 'ann'}], 'Enter in a text box applies; a kind alone filters by the kind');
    assert.equal(doc.activeElement, toggle);

    filters.sync([{key: 'by', value: 'jane'}, {key: 'by', value: 'joe'}]);
    fire(toggle, 'click');
    select.value = 'admin';
    fire(panel.querySelector('[data-role="panel-apply"]'), 'click');
    assert.deepEqual(applied[2], [{key: 'by', value: 'jane'}, {key: 'by', value: 'joe'},
        {key: 'channel', value: 'admin'}], 'two chips of one key stay when the admin changes another control');

    fire(toggle, 'click');
    fire(panel, 'keydown', {key: 'Escape'});
    assert.equal(panel.hidden, true, 'Esc closes the panel');
    assert.equal(doc.activeElement, toggle, 'the focus goes back to the Filters button');
});

/**
 * The range control's markup, as the page prints it, with the popover mounted on it
 *
 * @returns {{doc: FakeDocument, root: Object, part: function(string): Object, control: Object, applied: Array}}
 */
function rangeControl() {
    const doc = new FakeDocument();
    const el = builder(doc);
    const presets = ['hour', 'today', 'yesterday', '7d', '30d', 'custom'].map((code) =>
        el('button', {'data-role': 'preset', 'data-preset': code}, [el('span', {'data-role': 'preset-detail'})]));
    const root = el('div', {'data-role': 'range'}, [
        el('button', {'data-role': 'range-toggle', 'aria-expanded': 'false'}, [
            el('strong', {'data-role': 'range-label'}), el('span', {'data-role': 'range-detail'})
        ]),
        el('div', {'data-role': 'range-popover', hidden: true}, presets.concat([
            el('input', {'data-input': 'fromDate', type: 'date'}), el('input', {'data-input': 'fromTime', type: 'time'}),
            el('input', {'data-input': 'toDate', type: 'date'}), el('input', {'data-input': 'toTime', type: 'time'}),
            el('button', {'data-role': 'month-prev'}), el('span', {'data-role': 'month-label'}),
            el('button', {'data-role': 'month-next'}), el('div', {'data-role': 'weekdays'}),
            el('div', {'data-role': 'days'}), el('div', {'data-role': 'calendar-hint'}),
            el('div', {'data-role': 'range-error', hidden: true}),
            el('button', {'data-role': 'range-cancel'}), el('button', {'data-role': 'range-apply'})
        ]))
    ]);
    const applied = [];
    doc.body.appendChild(root);
    const part = (role) => root.querySelector('[data-role="' + role + '"]');
    const control = load('search-page/range-popover').create(root, {
        zone: copenhagen(),
        now: () => NOW,
        words: WORDS,
        onApply: (value) => applied.push(plain(value)),
        signal: new AbortController().signal
    });

    return {doc, root, part, control, applied};
}

test('range control: the button reads the range; the popover applies a preset or reports a typed mistake', () => {
    const zone = copenhagen();
    const range = load('search/range');
    const {doc, root, part, control, applied} = rangeControl();

    control.render(range.preset('7d', NOW, zone).range);
    assert.equal(part('range-label').textContent, 'Last 7 days');
    assert.match(part('range-detail').textContent, /16 Oct.*00:00.*22 Oct.*14:30/);

    fire(part('range-toggle'), 'click');
    assert.equal(part('range-popover').hidden, false);
    assert.equal(part('range-toggle').getAttribute('aria-expanded'), 'true');
    assert.equal(root.querySelector('[data-preset="7d"]').getAttribute('aria-pressed'), 'true');
    assert.equal(part('days').children.length, 42);
    assert.equal(root.querySelector('[data-input="toDate"]').max, '2026-10-22', 'no day to come can be typed');

    fire(root.querySelector('[data-preset="30d"]'), 'click');
    fire(part('range-apply'), 'click');
    assert.deepEqual(applied, [plain(range.preset('30d', NOW, zone).range)]);
    assert.equal(part('range-popover').hidden, true);

    fire(part('range-toggle'), 'click');
    const toDate = root.querySelector('[data-input="toDate"]');
    toDate.value = '';
    fire(toDate, 'change');
    fire(part('range-apply'), 'click');
    assert.equal(part('range-error').textContent, 'Enter the end date.');
    assert.equal(applied.length, 1);

    fire(part('range-popover'), 'keydown', {key: 'Escape'});
    assert.equal(part('range-popover').hidden, true, 'Esc drops the draft');
    assert.equal(doc.activeElement, part('range-toggle'));
});

test('range control: picking days keeps the focus in the popover, so Esc still closes it', () => {
    const range = load('search/range');
    const {doc, root, part, control} = rangeControl();
    const day = (key) => root.querySelector('button[data-day="' + key + '"]');

    control.render(range.preset('7d', NOW, copenhagen()).range);
    fire(part('range-toggle'), 'click');

    day('2026-10-12').focus();
    fire(day('2026-10-12'), 'click');
    assert.equal(doc.activeElement, day('2026-10-12'), 'the day picked, drawn anew, has the focus');

    doc.body.focus();
    fire(day('2026-10-14'), 'click');
    assert.equal(doc.activeElement, day('2026-10-14'),
        'a browser that does not focus a clicked button still leaves the focus on the day picked');
    assert.equal(root.querySelector('[data-input="toDate"]').value, '2026-10-14');

    fire(doc.activeElement, 'keydown', {key: 'Escape'});
    assert.equal(part('range-popover').hidden, true, 'Esc from the calendar closes the popover');
    assert.equal(doc.activeElement, part('range-toggle'));

    fire(part('range-toggle'), 'click');
    part('month-prev').focus();
    fire(part('month-prev'), 'click');
    assert.equal(doc.activeElement, part('month-prev'), 'a control that stays keeps the focus');
    part('month-next').focus();
    fire(part('month-next'), 'click');
    assert.equal(part('month-next').disabled, true, 'the current month is the last one shown');
    assert.notEqual(doc.activeElement, part('month-next'));
    assert.ok(part('range-popover').contains(doc.activeElement), 'a control disabled hands the focus on, inside');
    fire(doc.body, 'keydown', {key: 'Escape'});
    assert.equal(part('range-popover').hidden, true, 'Esc closes the popover whatever had the focus');
});

test('latest save: saves go one at a time, and of those that waited only the newest is sent', async () => {
    const latestSave = load('search/latest-save');
    const sent = [];
    const answers = [];
    const saves = latestSave.create((value) => {
        sent.push(value);

        return new Promise((resolve, reject) => answers.push({resolve, reject}));
    });
    const flush = () => new Promise((resolve) => setImmediate(resolve));

    saves.save('grid');
    await flush();
    saves.save('timeline');
    saves.save('grid, changes only');
    await flush();
    assert.deepEqual(sent, ['grid'], 'a save waits for the one on its way');

    answers[0].resolve();
    await flush();
    assert.deepEqual(sent, ['grid', 'grid, changes only'], 'the newest waiting save replaces the others');

    answers[1].reject(new Error('offline'));
    await flush();
    saves.save('timeline');
    await flush();
    assert.deepEqual(sent, ['grid', 'grid, changes only', 'timeline'], 'a failed save does not stop the next');
});
