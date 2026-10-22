/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The Filters button and the filters panel: one control per search key the panel shows, filled from the chips and
 * applied as chips (search/panel-model.js).
 *
 * The panel and the search bar stay in sync: opening the panel, and any change of the chips while it is open, shows
 * the chips in the controls; "Apply Filters", or Enter in a text box, reports the chips the panel stands for and
 * closes the panel unless they are refused. Only the controls the admin changed replace chips: the others keep theirs
 * as they are. A chip value a select does not list stays selectable, so applying the panel never drops it. Closing
 * the panel, by Apply, Cancel or Esc, gives the focus back to the Filters button. Renders with textContent and created
 * elements only.
 *
 * Markup it works on: the toggle button; in the panel, controls carrying data-filter-key (a select's options may
 * carry data-target, the key a value of its companion box is searched under), companion text boxes carrying
 * data-companion-of (the select's key), a "panel-error" element and the "panel-cancel" and "panel-apply" buttons
 * (data-role).
 */
define([
    'Hryvinskyi_ActivityLog/js/search/panel-model',
    'Hryvinskyi_ActivityLog/js/search/offer-view'
], function (panelModel, offerView) {
    'use strict';

    /**
     * @param {HTMLElement} toggle The Filters button
     * @param {HTMLElement} panel
     * @param {{words: Object, onApply: function(Array<{key: string, value: string}>), signal: AbortSignal}} options
     *     onApply receives the chips the applied panel stands for
     * @returns {{sync: function(Array<{key: string, value: string}>)}}
     */
    function create(toggle, panel, options) {
        var doc = panel.ownerDocument,
            controls = Array.prototype.slice.call(panel.querySelectorAll('[data-filter-key]')),
            error = panel.querySelector('[data-role="panel-error"]'),
            listen = function (target, type, handler) {
                target.addEventListener(type, handler, {signal: options.signal});
            },
            chips = [];

        /**
         * @param {HTMLElement} control
         * @returns {?HTMLInputElement} The companion text box of a select
         */
        function companionOf(control) {
            return panel.querySelector('[data-companion-of="' + control.dataset.filterKey + '"]');
        }

        /**
         * @returns {Array<Object>} The controls as search/panel-model.js reads them
         */
        function model() {
            return controls.map(function (control) {
                var isSelect = control.tagName === 'SELECT';

                return {
                    key: control.dataset.filterKey,
                    select: isSelect,
                    options: isSelect ? Array.prototype.filter.call(control.options, function (option) {
                        return option.value !== '';
                    }).map(function (option) {
                        return {value: option.value, target: option.dataset.target || ''};
                    }) : [],
                    companion: companionOf(control) !== null
                };
            });
        }

        /**
         * @param {HTMLSelectElement} select
         * @param {string} value
         */
        function selectValue(select, value) {
            var listed = Array.prototype.some.call(select.options, function (option) {
                    return option.value === value;
                }),
                extra;

            select.querySelectorAll('option[data-chip-value]').forEach(function (option) {
                if (option.value !== value) {
                    option.remove();
                }
            });
            if (!listed) {
                extra = doc.createElement('option');
                extra.value = value;
                extra.textContent = value;
                extra.dataset.chipValue = 'true';
                select.appendChild(extra);
            }
            select.value = value;
        }

        /** Shows the chips in the controls. */
        function fill() {
            var values = panelModel.fill(chips, model());

            controls.forEach(function (control) {
                var key = control.dataset.filterKey,
                    companion = companionOf(control);

                if (control.tagName === 'SELECT') {
                    selectValue(control, values.fields[key]);
                } else {
                    control.value = values.fields[key];
                }
                if (companion !== null) {
                    companion.value = values.companions[key];
                }
            });
        }

        /**
         * @param {?string} code
         */
        function showError(code) {
            error.textContent = offerView.refusal(code, options.words);
            error.hidden = error.textContent === '';
        }

        /**
         * @param {boolean} open
         */
        function setOpen(open) {
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
            toggle.classList.toggle('activity-search__filters-button--open', open);
            showError(null);
            if (open) {
                fill();
                if (controls.length > 0) {
                    controls[0].focus();
                }
            }
        }

        /** Reports the chips the panel stands for and closes it, unless they are refused. */
        function apply() {
            var values = {fields: {}, companions: {}},
                applied;

            controls.forEach(function (control) {
                var companion = companionOf(control);

                values.fields[control.dataset.filterKey] = control.value;
                if (companion !== null) {
                    values.companions[control.dataset.filterKey] = companion.value;
                }
            });
            applied = panelModel.apply(chips, values, model());
            if (!applied.ok) {
                showError(applied.error);

                return;
            }
            setOpen(false);
            toggle.focus();
            options.onApply(applied.chips);
        }

        listen(toggle, 'click', function () {
            setOpen(panel.hidden);
        });
        listen(panel.querySelector('[data-role="panel-cancel"]'), 'click', function () {
            setOpen(false);
            toggle.focus();
        });
        listen(panel.querySelector('[data-role="panel-apply"]'), 'click', apply);
        listen(panel, 'keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                setOpen(false);
                toggle.focus();

                return;
            }
            if (event.key === 'Enter' && event.target.tagName === 'INPUT') {
                event.preventDefault();
                apply();
            }
        });

        return Object.freeze({
            /**
             * @param {Array<{key: string, value: string}>} next The chips in the bar
             */
            sync: function (next) {
                chips = next.slice();
                if (!panel.hidden) {
                    fill();
                }
            }
        });
    }

    return Object.freeze({create: create});
});
