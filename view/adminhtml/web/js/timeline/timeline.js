/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The activity timeline: the results of one search, grouped by the store's day under sticky day headers, one
 * sentence per activity (timeline/item.js), with its states — skeletons while loading, the empty log with example
 * searches, no results with the ways out, the narrow-the-search notice, a failure with Retry — and "Load 50 more"
 * through the search's keyset cursor.
 *
 * Public surface (stable for any page that shows activities):
 *
 *     var timeline = timelineModule.mount(root, options);
 *     timeline.show({chips, range: {preset, from, to}, changesOnly});   // draw this search, newest first
 *     timeline.stop();                                                    // drop what is in flight, keep the drawing
 *     timeline.destroy();                                                 // drop everything, empty the root
 *
 * options:
 * - assets: timeline/assets.read(document) — the page includes the layout handle
 *   "hryvinskyi_activitylog_timeline_assets";
 * - search: the search port, {page(query, signal): Promise<page>} — timeline/search-client.create(...);
 * - clock: {now(): number} — search/clock.js; zone: search/zone.create(...); locale: the admin's locale;
 * - describeRange(range): {label, span} — the range's name ("Last 7 days") and its two ends written out;
 * - report(error): where failures go for the people who maintain the page (they are never shown as they are);
 * - keys (optional): the search key descriptions, for highlighting the rows a field filter matches;
 * - searchLink(chips) (optional): the address of the activity log searching for those chips, or null — the actor
 *   and the request become links to it;
 * - ask(criterion) (optional): filter the shown search in place instead, {key, value, alone: true} asking for that
 *   filter alone — the actor ("by") and the request ("request");
 * - actions (optional): widen(), removeLast(), clearAll(), example(index) — a way out of an empty result is offered
 *   only when its action is given;
 * - examples (optional): [{title, chips: [{key, value}], rangeLabel}] — the cards of the empty log;
 * - onPage(search, page) (optional): told the first page of every search shown (its counts, for a summary);
 * - pageSize (optional): activities per page, 50 unless given;
 * - countLine (optional): false leaves out the line above the results that counts them and names the range, for a
 *   host that shows its own count; shown unless false;
 * - noResults(search, page) (optional): the host's own words for a search that found nothing, {title, text}; the
 *   ways out are offered as without it (each only when its action is given). Without it the title names the range
 *   and the text says what was found outside it.
 *
 * Mounting is idempotent per root. Only the latest search draws: a newer show(), stop() or destroy() aborts every
 * request of the one before, and an answer that arrives anyway is dropped; "Load more" appends only to the search
 * whose cursor it follows. A control that starts another search (Retry, a way out of an empty result, an example)
 * is drawn away with the old results, so the focus moves to the results themselves.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/request',
    'Hryvinskyi_ActivityLog/js/search/range',
    'Hryvinskyi_ActivityLog/js/search/sentence',
    'Hryvinskyi_ActivityLog/js/search/days',
    'Hryvinskyi_ActivityLog/js/search/wording',
    'Hryvinskyi_ActivityLog/js/timeline/results',
    'Hryvinskyi_ActivityLog/js/timeline/item'
], function (request, rangeModule, sentence, days, wording, results, itemModule) {
    'use strict';

    var PAGE_SIZE = 50,
        ABORTED = 'aborted',
        REFUSED = 'refused',
        mounted = new WeakMap();

    /**
     * @param {HTMLElement} root
     * @param {Object} options See the module notes
     * @returns {{show: function(Object), stop: function(), destroy: function()}}
     */
    function build(root, options) {
        var assets = options.assets,
            words = assets.config.wording,
            tag = String(options.locale).replace(/_/g, '-'),
            numbers = new Intl.NumberFormat(tag),
            durations = new Intl.NumberFormat(tag, {style: 'unit', unit: 'millisecond', maximumFractionDigits: 0}),
            relativeTime = new Intl.RelativeTimeFormat(tag, {numeric: 'auto'}),
            actions = options.actions || {},
            pageSize = options.pageSize || PAGE_SIZE,
            lifetime = new AbortController(),
            frame = assets.clone('results'),
            part = function (name) {
                return frame.querySelector('[data-slot="' + name + '"]');
            },
            parts = {
                head: part('head'),
                count: part('count'),
                noun: part('noun'),
                span: part('span'),
                notice: part('notice'),
                list: part('list'),
                paging: part('paging'),
                more: part('more'),
                loaded: part('loaded'),
                status: part('status'),
                live: part('live')
            },
            formatNumber = function (value) {
                return numbers.format(value);
            },
            items = itemModule.create(assets, {
                zone: options.zone,
                formatNumber: formatNumber,
                formatDuration: function (ms) {
                    return durations.format(ms);
                },
                relative: function (ms) {
                    var distance = days.ago(ms, options.clock.now());

                    return relativeTime.format(distance.value, distance.unit);
                },
                searchLink: typeof options.searchLink === 'function' ? options.searchLink : null,
                asks: typeof options.ask === 'function'
            }),
            DAY_NAMES = Object.freeze({today: words.dayToday, yesterday: words.dayYesterday, earlier: '%1'}),
            current = null,
            STATES,
            ACTIONS,
            controller;

        /**
         * @param {string} tagName
         * @param {string} className
         * @param {string=} text
         * @returns {HTMLElement}
         */
        function element(tagName, className, text) {
            var node = root.ownerDocument.createElement(tagName);

            node.className = className;
            if (text !== undefined) {
                node.textContent = text;
            }

            return node;
        }

        /**
         * @param {string} action
         * @param {string} label
         * @param {string} className
         * @returns {HTMLElement}
         */
        function actionButton(action, label, className) {
            var button = element('button', className, label);

            button.setAttribute('type', 'button');
            button.setAttribute('data-action', action);

            return button;
        }

        /**
         * @param {boolean} isBusy
         * @param {string} announcement Read out by assistive technology; empty once done
         */
        function busy(isBusy, announcement) {
            frame.setAttribute('aria-busy', String(isBusy));
            parts.live.textContent = announcement;
        }

        /** Empties every part of the results. */
        function clear() {
            parts.head.hidden = true;
            parts.notice.hidden = true;
            parts.paging.hidden = true;
            parts.list.replaceChildren();
            parts.status.replaceChildren();
        }

        /**
         * @param {Object} generation
         */
        function drawHead(generation) {
            var line;

            if (options.countLine === false) {
                parts.head.hidden = true;

                return;
            }
            line = results.head(generation.search, generation.total, generation.loaded, words, formatNumber);
            parts.head.hidden = false;
            parts.count.textContent = line.count;
            parts.noun.textContent = line.noun;
            parts.span.textContent = ' · ' + options.describeRange(generation.search.range).span + ' · '
                + words.newestFirst;
        }

        /**
         * @param {Object} generation
         */
        function drawPaging(generation) {
            var line = results.paging(generation.nextCursor, generation.total, generation.loaded, pageSize, words,
                formatNumber);

            parts.paging.hidden = line === null;
            parts.more.disabled = false;
            parts.more.removeAttribute('aria-busy');
            parts.more.textContent = line === null ? '' : line.label;
            parts.loaded.textContent = line === null ? '' : line.note;
        }

        /**
         * @param {{narrowed: boolean}} page
         */
        function drawNotice(page) {
            var notice = results.narrow(page, assets.config.candidateLimit, words, formatNumber);

            if (notice !== null) {
                parts.notice.textContent = notice;
                parts.notice.hidden = false;
            }
        }

        /**
         * @param {{key: string, firstMs: number}} group
         * @returns {{key: string, section: HTMLElement, count: HTMLElement, size: number}}
         */
        function daySection(group) {
            var section = assets.clone('day'),
                relation = days.relation(group.firstMs, options.clock.now(), options.zone);

            section.querySelector('[data-slot="label"]').textContent = wording.fill(
                DAY_NAMES[relation],
                options.zone.format(group.firstMs, 'day')
            );
            parts.list.appendChild(section);

            return {
                key: group.key,
                section: section,
                count: section.querySelector('[data-slot="count"]'),
                size: 0
            };
        }

        /**
         * Adds activities under their days, continuing the last day drawn
         *
         * @param {Object} generation
         * @param {Array<Object>} events Newest first, older than everything drawn
         * @returns {?HTMLElement} The first activity added
         */
        function append(generation, events) {
            var first = null;

            days.group(events, options.zone).forEach(function (group) {
                var day = generation.lastDay !== null && generation.lastDay.key === group.key
                    ? generation.lastDay
                    : daySection(group);

                group.events.forEach(function (event) {
                    var node = items.render(sentence.build(event, generation.terms));

                    day.section.appendChild(node);
                    first = first === null ? node : first;
                });
                day.size += group.events.length;
                day.count.textContent = results.dayCount(day.size, words, formatNumber);
                generation.lastDay = day;
            });
            generation.loaded += events.length;

            return first;
        }

        /**
         * How each state of a first page is drawn; every state results.stateOf gives has one
         */
        STATES = Object.freeze({
            results: function (generation, page) {
                append(generation, page.events);
                drawHead(generation);
                drawNotice(page);
                drawPaging(generation);
            },
            noResults: function (generation, page) {
                var wider = rangeModule.preset(results.WIDEN_TO, options.clock.now(), options.zone),
                    way = results.noResults(generation.search, page, words, formatNumber, function (ms) {
                        return options.zone.format(ms, 'long');
                    }, wider.ok ? wider.range : null),
                    box = assets.clone('state'),
                    own = typeof options.noResults === 'function' ? options.noResults(generation.search, page) : null,
                    buttons = box.querySelector('[data-slot="actions"]'),
                    offer = function (allowed, handler, action, label, className) {
                        if (allowed && typeof handler === 'function') {
                            buttons.appendChild(actionButton(action, label, className));
                        }
                    };

                drawHead(generation);
                drawNotice(page);
                box.querySelector('[data-slot="title"]').textContent = own === null
                    ? wording.fill(words.noResultsTitle, options.describeRange(generation.search.range).label)
                    : own.title;
                box.querySelector('[data-slot="text"]').textContent = own === null ? way.hint : own.text;
                offer(way.widen, actions.widen, 'widen', words.widen, 'activity-stream__primary');
                offer(way.removeLast, actions.removeLast, 'remove-last', words.removeLast,
                    'activity-stream__secondary');
                offer(way.clearAll, actions.clearAll, 'clear-all', words.clearAll, 'activity-stream__link-button');
                parts.status.appendChild(box);
            },
            emptyLog: function () {
                var box = assets.clone('empty-log'),
                    cards = box.querySelector('[data-slot="examples"]'),
                    examples = typeof actions.example === 'function' ? options.examples || [] : [];

                box.querySelector('[data-slot="title"]').textContent = words.emptyLogTitle;
                box.querySelector('[data-slot="text"]').textContent = words.emptyLogText;
                examples.forEach(function (example, index) {
                    var card = assets.clone('example'),
                        chips = card.querySelector('[data-slot="chips"]');

                    card.setAttribute('data-action', 'example');
                    card.setAttribute('data-index', String(index));
                    card.querySelector('[data-slot="title"]').textContent = example.title;
                    example.chips.forEach(function (chip) {
                        var shown = element('span', 'activity-stream__example-chip');

                        shown.appendChild(element('span', 'activity-stream__example-key', chip.key + ':'));
                        shown.appendChild(element('span', '', chip.value));
                        chips.appendChild(shown);
                    });
                    chips.appendChild(element('span', 'activity-stream__muted', '· ' + example.rangeLabel));
                    cards.appendChild(card);
                });
                cards.hidden = examples.length === 0;
                parts.status.appendChild(box);
            }
        });

        /**
         * @param {Object} generation
         * @param {Object} failure A SearchFailure, or anything a port threw
         * @returns {boolean} Whether the failure is worth telling (the search was not given up)
         */
        function worthTelling(generation, failure) {
            if (generation !== current || (failure && failure.kind === ABORTED)) {
                return false;
            }
            options.report(failure);

            return true;
        }

        /**
         * @param {Object} failure
         */
        function drawFailure(failure) {
            var box = assets.clone('state');

            busy(false, '');
            clear();
            box.querySelector('[data-slot="title"]').textContent = words.loadError;
            box.querySelector('[data-slot="text"]').textContent = failure && failure.kind === REFUSED
                ? failure.reason
                : '';
            box.querySelector('[data-slot="actions"]').appendChild(
                actionButton('retry', words.retry, 'activity-stream__secondary')
            );
            parts.status.appendChild(box);
        }

        /**
         * @param {Object} generation
         * @param {?string} cursor
         * @returns {Object} The query of a page of the generation's search
         */
        function query(generation, cursor) {
            return {
                range: {from: generation.search.range.from, to: generation.search.range.to},
                chips: generation.search.chips,
                changesOnly: generation.search.changesOnly,
                cursor: cursor,
                limit: pageSize
            };
        }

        /**
         * @param {Object} generation
         */
        function loadFirst(generation) {
            clear();
            parts.status.appendChild(assets.clone('skeleton'));
            busy(true, words.loading);
            options.search.page(query(generation, null), generation.controller.signal).then(function (page) {
                if (generation !== current) {
                    return;
                }
                busy(false, '');
                clear();
                generation.total = page.total;
                generation.nextCursor = page.nextCursor;
                STATES[results.stateOf(page)](generation, page);
                if (typeof options.onPage === 'function') {
                    options.onPage(generation.search, page);
                }
            }, function (failure) {
                if (worthTelling(generation, failure)) {
                    drawFailure(failure);
                }
            });
        }

        /** Loads the page after the last one shown, for the search shown. */
        function loadMore() {
            var generation = current;

            if (generation === null || generation.nextCursor === null || parts.more.disabled) {
                return;
            }
            parts.more.disabled = true;
            parts.more.setAttribute('aria-busy', 'true');
            options.search.page(query(generation, generation.nextCursor), generation.controller.signal)
                .then(function (page) {
                    var first;

                    if (generation !== current) {
                        return;
                    }
                    first = append(generation, page.events);
                    generation.nextCursor = page.nextCursor;
                    drawHead(generation);
                    drawNotice(page);
                    drawPaging(generation);
                    if (first !== null) {
                        first.focus();
                    }
                }, function (failure) {
                    if (worthTelling(generation, failure)) {
                        drawPaging(generation);
                        parts.loaded.textContent = words.loadError;
                    }
                });
        }

        /**
         * Opens or closes the request's other activities under an item, loading them the first time
         *
         * @param {HTMLElement} button
         */
        function toggleSiblings(button) {
            var node = button.closest('[data-activity]'),
                list = node.querySelector('[data-slot="sibling-list"]'),
                model = items.modelOf(node),
                generation = current,
                open = button.getAttribute('aria-expanded') !== 'true';

            button.setAttribute('aria-expanded', String(open));
            list.hidden = !open;
            if (!open || list.getAttribute('data-loaded') === 'true' || generation === null || model === null) {
                return;
            }
            button.setAttribute('aria-busy', 'true');
            list.textContent = words.loading;
            options.search.page(request.siblingsOf(model.correlationId, options.clock.now()),
                generation.controller.signal).then(function (page) {
                if (generation !== current) {
                    return;
                }
                button.removeAttribute('aria-busy');
                items.siblings(list, page.events.filter(function (event) {
                    return event.id !== model.id;
                }).map(sentence.sibling), page.nextCursor !== null);
                list.setAttribute('data-loaded', 'true');
            }, function (failure) {
                if (worthTelling(generation, failure)) {
                    button.removeAttribute('aria-busy');
                    list.textContent = words.loadError;
                }
            });
        }

        /**
         * @param {string} key
         * @returns {function(HTMLElement, Event)} Asks for the filter on the control alone, in place
         */
        function askFor(key) {
            return function (control, event) {
                if (typeof options.ask !== 'function'
                    || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                    return;
                }
                event.preventDefault();
                options.ask({key: key, value: control.getAttribute('data-value'), alone: true});
            };
        }

        /**
         * @param {function(HTMLElement, Event)} action An action that draws its control away
         * @returns {function(HTMLElement, Event)} The action, then the focus on the results
         */
        function thenFocusResults(action) {
            return function (control, event) {
                action(control, event);
                frame.focus();
            };
        }

        /**
         * @param {string} name
         * @returns {function()} Runs the action the page gave, if it gave one
         */
        function pageAction(name) {
            return thenFocusResults(function () {
                if (typeof actions[name] === 'function') {
                    actions[name]();
                }
            });
        }

        /**
         * What each control of the timeline does, by its data-action
         */
        ACTIONS = Object.freeze({
            diff: function (control) {
                items.toggle('diff', control);
            },
            same: function (control) {
                items.toggle('same', control);
            },
            unchanged: function (control) {
                items.toggle('unchanged', control);
            },
            siblings: toggleSiblings,
            actor: askFor('by'),
            request: askFor('request'),
            more: loadMore,
            retry: thenFocusResults(function () {
                if (current !== null) {
                    show(current.search);
                }
            }),
            widen: pageAction('widen'),
            'remove-last': pageAction('removeLast'),
            'clear-all': pageAction('clearAll'),
            example: thenFocusResults(function (control) {
                if (typeof actions.example === 'function') {
                    actions.example(Number(control.getAttribute('data-index')));
                }
            })
        });

        /** Drops what is in flight; the drawing stays. */
        function stop() {
            if (current !== null) {
                current.controller.abort();
                current = null;
            }
        }

        /**
         * Draws a search, from its first page
         *
         * @param {{chips: Array<{key: string, value: string}>, range: {preset: string, from: number, to: number},
         *     changesOnly: boolean}} search
         */
        function show(search) {
            stop();
            current = {
                search: search,
                controller: new AbortController(),
                terms: sentence.fieldTerms(search.chips, options.keys || []),
                total: null,
                loaded: 0,
                nextCursor: null,
                lastDay: null
            };
            loadFirst(current);
        }

        /** Drops everything and empties the root. */
        function destroy() {
            stop();
            lifetime.abort();
            root.replaceChildren();
            mounted.delete(root);
        }

        frame.setAttribute('tabindex', '-1');
        root.replaceChildren(frame);
        root.addEventListener('click', function (event) {
            var control = event.target.closest('[data-action]'),
                action = control === null || !root.contains(control) ? null : control.getAttribute('data-action');

            if (action !== null && Object.prototype.hasOwnProperty.call(ACTIONS, action)) {
                ACTIONS[action](control, event);
            }
        }, {signal: lifetime.signal});

        controller = Object.freeze({show: show, stop: stop, destroy: destroy});
        mounted.set(root, controller);

        return controller;
    }

    /**
     * Mounts the timeline in a root, once per root
     *
     * @param {HTMLElement} root
     * @param {Object} options See the module notes
     * @returns {{show: function(Object), stop: function(), destroy: function()}}
     * @throws {TypeError} Without a report function
     */
    function mount(root, options) {
        if (mounted.has(root)) {
            return mounted.get(root);
        }
        if (typeof options.report !== 'function') {
            throw new TypeError('The timeline needs a report function for its failures');
        }

        return build(root, options);
    }

    return Object.freeze({
        PAGE_SIZE: PAGE_SIZE,
        mount: mount
    });
});
