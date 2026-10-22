/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The activity search page: the search bar, the range, the filters panel, "Changes only", the example searches,
 * the Timeline/Grid toggle and the header's export, around one search.
 *
 * The page owns the search (search/search-state.js). Each change redraws the controls, writes the search into the
 * address bar (a link to it opens the same search), hands it to the view shown — the timeline (timeline/timeline.js)
 * or the grid — sums it up beside the options, and announces it on the results element (search/search-event.js) for
 * whatever else shows results there. A part of the page that offers a filter (a grid cell's actor, channel or
 * request) asks for it with the criterion event (search/criterion-event.js); the timeline asks through its "ask"
 * option, for its actor or request alone. The ways out of an empty result (widen the range, remove the last filter,
 * clear them all, an example search) change the search here.
 * The view and "Changes only" are remembered for the admin user when the admin changes them, one save at a time so
 * the last choice is the one kept; a link that switched "Changes only" off for what it asks is not remembered as a
 * choice. A new search clears the grid's selection, so rows chosen for another search never narrow an export.
 *
 * Every request has a deadline and ends with the page; the range is brought up to date before each one.
 */
define([
    'uiRegistry',
    'Hryvinskyi_ActivityLog/js/search/page-config',
    'Hryvinskyi_ActivityLog/js/search/clock',
    'Hryvinskyi_ActivityLog/js/search/zone',
    'Hryvinskyi_ActivityLog/js/search/request',
    'Hryvinskyi_ActivityLog/js/search/range',
    'Hryvinskyi_ActivityLog/js/search/deadline',
    'Hryvinskyi_ActivityLog/js/search/latest-save',
    'Hryvinskyi_ActivityLog/js/search/search-state',
    'Hryvinskyi_ActivityLog/js/search/search-event',
    'Hryvinskyi_ActivityLog/js/search/criterion-event',
    'Hryvinskyi_ActivityLog/js/timeline/assets',
    'Hryvinskyi_ActivityLog/js/timeline/search-client',
    'Hryvinskyi_ActivityLog/js/timeline/results',
    'Hryvinskyi_ActivityLog/js/timeline/timeline',
    'Hryvinskyi_ActivityLog/js/search-page/chip-bar',
    'Hryvinskyi_ActivityLog/js/search-page/range-popover',
    'Hryvinskyi_ActivityLog/js/search-page/filters-panel',
    'Hryvinskyi_ActivityLog/js/search-page/grid-link',
    'Hryvinskyi_ActivityLog/js/search-page/summary'
], function (
    registry,
    pageConfig,
    clockModule,
    zoneModule,
    request,
    rangeModule,
    deadline,
    latestSave,
    searchState,
    searchEvent,
    criterionEvent,
    timelineAssets,
    searchClient,
    results,
    timelineModule,
    chipBar,
    rangePopover,
    filtersPanel,
    gridLink,
    summaryLine
) {
    'use strict';

    var mounted = new WeakSet();

    /**
     * @param {HTMLElement} root
     * @returns {Object}
     */
    function readConfig(root) {
        var script = root.querySelector('script[data-role="search-config"]');

        return pageConfig.parse(JSON.parse(script ? script.textContent : 'null'));
    }

    /**
     * @param {*} failure
     */
    function warn(failure) {
        console.warn('Activity search:', failure);
    }

    /**
     * @param {HTMLElement} root
     * @returns {Function} Unmount
     */
    function mount(root) {
        var config = readConfig(root),
            lifetime = new AbortController(),
            signal = lifetime.signal,
            clock = clockModule.create(config.serverNowMs, Date.now(), Date.now),
            zone = zoneModule.create(config.timeZone, config.locale),
            numbers = new Intl.NumberFormat(config.locale.replace(/_/g, '-')),
            keys = config.keys.map(function (description) {
                return description.key;
            }),
            context = {keys: keys, changeChannels: config.changeChannels, channels: config.channels},
            part = function (role) {
                return root.querySelector('[data-role="' + role + '"]');
            },
            listen = function (target, type, handler) {
                if (target) {
                    target.addEventListener(type, handler, {signal: signal});
                }
            },
            area = part('search-results'),
            changesOnly = part('changes-only'),
            examples = part('examples'),
            grid = gridLink.create(registry, config.grid, config.timeoutMs),
            assets = timelineAssets.read(document),
            search = searchClient.create(config.searchUrl, config.timeoutMs, window),
            summary = summaryLine.create(part('summary'), {
                words: config.wording,
                formatNumber: formatNumber,
                countText: function (bounded) {
                    return results.countText(bounded, assets.config.wording, formatNumber);
                },
                search: search,
                report: warn,
                signal: signal
            }),
            chosen = {view: config.preferences.view, changesOnly: config.preferences.changesOnly},
            preferences = latestSave.create(sendChoice),
            state = searchState.initial({
                search: window.location.search,
                keys: keys,
                nowMs: clock.now(),
                zone: zone,
                preferences: config.preferences,
                changeChannels: config.changeChannels,
                channels: config.channels
            }),
            bar,
            range,
            panel,
            timeline;

        /**
         * @param {number} value
         * @returns {string}
         */
        function formatNumber(value) {
            return numbers.format(value);
        }

        /** Shows the chosen view and marks its button. */
        function showView() {
            searchState.VIEWS.forEach(function (view) {
                var area = part(view + '-view'),
                    button = root.querySelector('[data-role="view"][data-view="' + view + '"]');

                if (area) {
                    area.hidden = view !== state.view;
                }
                if (button) {
                    button.setAttribute('aria-pressed', String(view === state.view));
                }
            });
        }

        /** Redraws the controls and hands the search on. */
        function publish() {
            var current = searchState.snapshot(state, clock.now(), zone),
                query = searchState.linkQuery(state);

            bar.render(state.chips);
            range.render(current.range);
            panel.sync(state.chips);
            if (changesOnly) {
                changesOnly.checked = state.changesOnly;
            }
            if (examples) {
                examples.hidden = state.chips.length > 0;
            }
            window.history.replaceState(
                window.history.state,
                '',
                window.location.pathname + (query === '' ? '' : '?' + query) + window.location.hash
            );
            summary.follow(current, state.view);
            grid.clearSelection();
            if (state.view === 'grid') {
                timeline.stop();
                grid.show(searchState.gridParam(current));
            } else {
                timeline.show(current);
            }
            if (area) {
                area.dispatchEvent(new CustomEvent(searchEvent.NAME, {
                    detail: searchEvent.detail(current, state.view)
                }));
            }
        }

        /**
         * Takes a filter another part of the page asked for
         *
         * @param {{key: string, value: string, alone: (boolean|undefined)}} criterion
         */
        function take(criterion) {
            var added = searchState.withCriterion(state, criterion, context);

            if (added.ok) {
                state = added.state;
                publish();
            }
        }

        /**
         * Opens an example search
         *
         * @param {number} index
         */
        function pickExample(index) {
            var example = config.examples[index],
                picked = example ? searchState.withExample(state, example, keys, clock.now(), zone) : null;

            if (picked !== null && picked.ok) {
                state = picked.state;
                publish();
            }
        }

        /**
         * @param {Object} changes
         */
        function change(changes) {
            state = searchState.update(state, changes);
            publish();
        }

        /** Remembers the admin's view choices as they are now. */
        function remember() {
            preferences.save({view: chosen.view, changesOnly: chosen.changesOnly});
        }

        /**
         * Sends one save of the view choices; a failure only costs the memory, so it is logged
         *
         * @param {{view: string, changesOnly: boolean}} choice
         * @returns {Promise} Settles once the server answered or the request ended
         */
        function sendChoice(choice) {
            var form = new FormData(),
                limit = deadline.start(window, signal, config.timeoutMs);

            form.append('form_key', config.formKey);
            form.append('view', choice.view);
            form.append('changes', choice.changesOnly ? '1' : '0');

            return fetch(config.preferenceUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: form,
                signal: limit.signal
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Saving the view choice failed with status ' + response.status);
                }
            }).catch(function (error) {
                if (!signal.aborted) {
                    warn(error);
                }
            }).finally(limit.done);
        }

        /** Loads the values the search bar suggests, once. */
        function loadSuggestions() {
            var limit = deadline.start(window, signal, config.timeoutMs);

            fetch(config.suggestUrl, {
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                signal: limit.signal
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('The suggestions request failed with status ' + response.status);
                }

                return response.json();
            }).then(function (body) {
                if (!signal.aborted) {
                    bar.setVocabulary(request.parseSuggestions(body));
                }
            }).catch(function (error) {
                // The bar still offers the keys and takes typed values; only the lists of values are missing
                if (!signal.aborted) {
                    warn(error);
                }
            }).finally(limit.done);
        }

        bar = chipBar.create(part('bar'), {
            words: config.wording,
            keys: config.keys,
            idPrefix: root.id || 'activity-search',
            formatNumber: formatNumber,
            onChange: function (chips) {
                change({chips: chips});
            },
            signal: signal
        });
        range = rangePopover.create(part('range'), {
            zone: zone,
            now: clock.now,
            words: config.wording,
            onApply: function (applied) {
                change({range: applied});
            },
            signal: signal
        });
        panel = filtersPanel.create(part('filters-toggle'), part('filters-panel'), {
            words: config.wording,
            onApply: function (chips) {
                change({chips: chips});
            },
            signal: signal
        });

        timeline = timelineModule.mount(part('timeline-view'), {
            assets: assets,
            search: search,
            clock: clock,
            zone: zone,
            locale: config.locale,
            keys: config.keys,
            report: warn,
            describeRange: function (shown) {
                var described = rangeModule.describe(shown, zone, config.wording, 'long');

                return {label: described.label, span: described.from + ' – ' + described.to};
            },
            searchLink: function (chips) {
                var query = searchState.linkQuery({chips: chips, range: state.range});

                return window.location.pathname + (query === '' ? '' : '?' + query);
            },
            ask: take,
            actions: {
                widen: function () {
                    var wider = rangeModule.preset(results.WIDEN_TO, clock.now(), zone);

                    if (wider.ok) {
                        change({range: wider.range});
                    }
                },
                removeLast: function () {
                    change({chips: state.chips.slice(0, -1)});
                },
                clearAll: function () {
                    change({chips: []});
                },
                example: pickExample
            },
            examples: config.examples.map(function (example) {
                return {
                    title: example.title,
                    chips: example.chips,
                    rangeLabel: config.wording.presets[example.preset] || ''
                };
            }),
            onPage: summary.report
        });
        signal.addEventListener('abort', timeline.destroy, {once: true});

        listen(changesOnly, 'change', function () {
            chosen.changesOnly = changesOnly.checked;
            remember();
            change({changesOnly: changesOnly.checked});
        });
        root.querySelectorAll('[data-role="view"]').forEach(function (button) {
            listen(button, 'click', function () {
                if (button.dataset.view === state.view) {
                    return;
                }
                chosen.view = button.dataset.view;
                remember();
                state = searchState.update(state, {view: button.dataset.view});
                showView();
                publish();
            });
        });
        listen(examples, 'click', function (event) {
            var button = event.target.closest('[data-example]');

            if (button) {
                pickExample(Number(button.dataset.example));
            }
        });
        listen(part('export'), 'click', function () {
            grid.exportCsv(searchState.gridParam(searchState.snapshot(state, clock.now(), zone)));
        });
        listen(root, criterionEvent.NAME, function (event) {
            var criterion = criterionEvent.read(event.detail);

            if (criterion !== null) {
                take(criterion);
            }
        });

        showView();
        publish();
        loadSuggestions();

        return function unmount() {
            lifetime.abort();
        };
    }

    /**
     * Entry point for data-mage-init.
     *
     * @param {Object} config Unused: the page reads its config from its JSON script element
     * @param {HTMLElement} root
     * @returns {?Function} Unmount
     */
    return function (config, root) {
        if (mounted.has(root)) {
            return null;
        }

        mounted.add(root);

        return mount(root);
    };
});
