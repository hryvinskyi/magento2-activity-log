/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The activity dashboard: the period and channel controls, the summary and every widget.
 *
 * Changing the period or a channel reloads everything once; a newer request cancels an older one, so a late answer
 * never replaces a newer view. Every request has a deadline. The choice is remembered for the admin user.
 */
define([
    'Hryvinskyi_ActivityLog/js/dashboard/response-parser',
    'Hryvinskyi_ActivityLog/js/dashboard/channel-selection',
    'Hryvinskyi_ActivityLog/js/dashboard/summary-view',
    'Hryvinskyi_ActivityLog/js/dashboard/widget-renderers'
], function (parser, channelSelection, summaryView, rendererFor) {
    'use strict';

    var mounted = new WeakSet();

    /**
     * @param {HTMLElement} root
     * @returns {Object}
     */
    function readConfig(root) {
        var script = root.querySelector('script[data-role="dashboard-config"]');

        return parser.parseConfig(JSON.parse(script ? script.textContent : 'null'));
    }

    /**
     * @param {string} tag
     * @param {string} className
     * @param {string} text
     * @returns {HTMLElement}
     */
    function element(tag, className, text) {
        var node = document.createElement(tag);

        node.className = className;
        node.textContent = text;

        return node;
    }

    /**
     * @param {Object} config
     * @param {HTMLElement} root
     */
    function mount(config, root) {
        var settings = readConfig(root),
            wording = settings.wording,
            chipOrder = Array.prototype.map.call(root.querySelectorAll('[data-role="channel"]'), function (chip) {
                return chip.dataset.code;
            }),
            state = {periodDays: settings.periodDays, channels: settings.channels.slice(), asTable: {}, data: null},
            drawn = {},
            inFlight = null,
            lifetime = new AbortController();

        /** Marks the period and channel controls. */
        function syncControls() {
            root.querySelectorAll('[data-role="period"]').forEach(function (button) {
                button.setAttribute('aria-pressed', String(Number(button.dataset.days) === state.periodDays));
            });
            root.querySelectorAll('[data-role="channel"]').forEach(function (chip) {
                chip.setAttribute('aria-pressed', String(state.channels.indexOf(chip.dataset.code) !== -1));
            });
        }

        /**
         * @param {string} code
         * @returns {?HTMLElement}
         */
        function widgetBody(code) {
            var section = root.querySelector('[data-role="widget"][data-code="' + CSS.escape(code) + '"]');

            return section ? section.querySelector('[data-role="widget-body"]') : null;
        }

        /**
         * @param {string} code
         */
        function clearWidget(code) {
            var body = widgetBody(code);

            if (drawn[code]) {
                drawn[code].destroy();
                delete drawn[code];
            }

            if (body) {
                body.replaceChildren();
            }
        }

        /**
         * @param {string} code
         * @param {string} title
         * @param {string} hint
         * @param {boolean} withRetry
         */
        function drawState(code, title, hint, withRetry) {
            var body = widgetBody(code),
                box = element('div', 'activity-widget__state', ''),
                retry;

            if (!body) {
                return;
            }

            box.appendChild(element('div', 'activity-widget__state-title', title));
            box.appendChild(element('div', 'activity-widget__state-hint', hint));

            if (withRetry) {
                retry = element('button', 'action-default', wording.retry);
                retry.type = 'button';
                retry.addEventListener('click', load, {signal: lifetime.signal});
                box.appendChild(retry);
            }

            body.appendChild(box);
        }

        /**
         * @param {string} code
         */
        function drawWidget(code) {
            var widget = state.data.widgets[code],
                renderer = rendererFor((settings.widgets[code] || {}).viewType),
                body = widgetBody(code),
                section;

            clearWidget(code);

            if (!body) {
                return;
            }

            if (!widget || widget.status !== 'ok' || !renderer) {
                drawState(code, wording.error, '', true);

                return;
            }

            if (renderer.isEmpty(widget.data)) {
                drawState(code, wording.emptyTitle, wording.emptyHint, false);

                return;
            }

            section = body.closest('[data-role="widget"]');
            drawn[code] = renderer.render(body, widget.data, {
                asTable: Boolean(state.asTable[code]),
                wording: wording,
                title: section ? section.getAttribute('aria-label') || '' : ''
            });
        }

        /** Shows the loaded data. */
        function draw() {
            summaryView.render(root.querySelector('[data-role="summary"]'), state.data.summary, wording, state.periodDays);
            Object.keys(settings.widgets).forEach(drawWidget);
            root.querySelector('[data-role="updated"]').textContent = wording.updated.replace(
                '%1',
                new Intl.DateTimeFormat(undefined, {hour: 'numeric', minute: '2-digit'}).format(new Date())
            );
        }

        /**
         * @param {boolean} visible
         */
        function showLoadError(visible) {
            var banner = root.querySelector('[data-role="load-error"]');

            banner.hidden = !visible;
            banner.querySelector('[data-role="load-error-text"]').textContent = wording.loadError;
        }

        /** Loads the data for the current choice, cancelling any request still running. */
        function load() {
            var request = new AbortController(),
                timer = window.setTimeout(function () {
                    request.abort();
                }, settings.timeoutMs),
                url = new URL(settings.dataUrl, window.location.href);

            if (inFlight) {
                inFlight.abort();
            }

            inFlight = request;
            url.searchParams.set('period', String(state.periodDays));
            state.channels.forEach(function (channel) {
                url.searchParams.append('channels[]', channel);
            });
            root.classList.add('activity-dashboard--loading');

            fetch(url.toString(), {
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                signal: request.signal
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Dashboard data request failed with status ' + response.status);
                }

                return response.json();
            }).then(function (body) {
                if (inFlight !== request) {
                    return;
                }

                state.data = parser.parseData(body);
                showLoadError(false);
                draw();
            }).catch(function (error) {
                if (inFlight !== request || lifetime.signal.aborted) {
                    return;
                }

                console.error('Activity dashboard:', error);
                showLoadError(true);
            }).finally(function () {
                window.clearTimeout(timer);

                if (inFlight === request) {
                    inFlight = null;
                    root.classList.remove('activity-dashboard--loading');
                }
            });
        }

        /** Remembers the choice for the admin user; a failure only costs the memory, so it is logged. */
        function savePreference() {
            var form = new FormData();

            form.append('form_key', settings.formKey);
            form.append('period', String(state.periodDays));
            state.channels.forEach(function (channel) {
                form.append('channels[]', channel);
            });

            fetch(settings.preferenceUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: form,
                signal: lifetime.signal
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Saving the dashboard choice failed with status ' + response.status);
                }
            }).catch(function (error) {
                if (!lifetime.signal.aborted) {
                    console.warn('Activity dashboard:', error);
                }
            });
        }

        /** Applies a new choice. */
        function choose() {
            syncControls();
            savePreference();
            load();
        }

        /**
         * @param {HTMLElement} toggle
         */
        function toggleTable(toggle) {
            var section = toggle.closest('[data-role="widget"]'),
                code = section ? section.dataset.code : '';

            if (!code || !state.data) {
                return;
            }

            state.asTable[code] = !state.asTable[code];
            toggle.setAttribute('aria-pressed', String(state.asTable[code]));
            toggle.textContent = state.asTable[code] ? wording.showChart : wording.showTable;
            drawWidget(code);
        }

        var clickHandlers = Object.freeze({
            /** @param {HTMLElement} button */
            period: function (button) {
                state.periodDays = Number(button.dataset.days);
                choose();
            },
            /** @param {HTMLElement} chip */
            channel: function (chip) {
                state.channels = channelSelection.toggle(chipOrder, state.channels, chip.dataset.code);
                choose();
            },
            'toggle-table': toggleTable,
            refresh: load,
            'retry-all': load
        });

        root.addEventListener('click', function (event) {
            var target = event.target.closest('[data-role]'),
                handler = target && Object.prototype.hasOwnProperty.call(clickHandlers, target.dataset.role)
                    ? clickHandlers[target.dataset.role]
                    : null;

            if (handler) {
                handler(target);
            }
        }, {signal: lifetime.signal});

        syncControls();
        load();

        return function unmount() {
            lifetime.abort();

            if (inFlight) {
                inFlight.abort();
            }

            Object.keys(drawn).forEach(clearWidget);
        };
    }

    /**
     * Entry point for data-mage-init.
     *
     * @param {Object} config
     * @param {HTMLElement} root
     * @returns {?Function} Unmount
     */
    return function (config, root) {
        if (mounted.has(root)) {
            return null;
        }

        mounted.add(root);

        return mount(config, root);
    };
});
