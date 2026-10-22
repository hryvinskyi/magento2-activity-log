/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The time range button and its popover: presets, date and time inputs, a calendar, Cancel and Apply.
 *
 * The button shows the range it is given; the popover edits a draft of it (search/range-draft.js) and reports the
 * range applied. Cancel, Esc and a click outside drop the draft. Times are the store's (the zone passed in) to the
 * minute. The focus stays in the open popover while the calendar is redrawn: a day picked keeps it. Renders with
 * textContent and created elements only.
 *
 * Markup it works on (data-role): "range-toggle", "range-label", "range-detail", "range-popover", "preset" buttons
 * with data-preset holding a "preset-detail", inputs with data-input ("fromDate", "fromTime", "toDate", "toTime"),
 * "month-prev", "month-label", "month-next", "weekdays", "days", "calendar-hint", "range-error", "range-cancel" and
 * "range-apply".
 */
define([
    'Hryvinskyi_ActivityLog/js/search/range',
    'Hryvinskyi_ActivityLog/js/search/range-draft'
], function (range, rangeDraft) {
    'use strict';

    /**
     * @param {HTMLElement} root The range control
     * @param {{zone: Object, now: function(): number, words: Object,
     *     onApply: function({preset: string, from: number, to: number}), signal: AbortSignal}} options
     * @returns {{render: function(Object)}}
     */
    function create(root, options) {
        var doc = root.ownerDocument,
            words = options.words,
            zone = options.zone,
            part = function (role) {
                return root.querySelector('[data-role="' + role + '"]');
            },
            toggle = part('range-toggle'),
            popover = part('range-popover'),
            days = part('days'),
            error = part('range-error'),
            listen = function (target, type, handler) {
                target.addEventListener(type, handler, {signal: options.signal});
            },
            current = null,
            draft = null;

        /**
         * @param {string} tag
         * @param {string} className
         * @param {string} text
         * @returns {HTMLElement}
         */
        function element(tag, className, text) {
            var node = doc.createElement(tag);

            node.className = className;
            node.textContent = text;

            return node;
        }

        /**
         * @param {?string} code An error of range.custom, or null
         */
        function showError(code) {
            error.textContent = code === null ? '' : words[code] || '';
            error.hidden = error.textContent === '';
        }

        /** Draws the open popover from the draft. */
        function draw() {
            var view = rangeDraft.view(draft, options.now(), zone, words),
                today = rangeDraft.dayKey(options.now(), zone);

            view.presets.forEach(function (preset) {
                var button = root.querySelector('[data-role="preset"][data-preset="' + preset.code + '"]');

                if (button) {
                    button.setAttribute('aria-pressed', String(preset.active));
                    button.querySelector('[data-role="preset-detail"]').textContent = preset.detail;
                }
            });
            root.querySelectorAll('input[data-input]').forEach(function (input) {
                var value = draft[input.dataset.input];

                if (input.value !== value) {
                    input.value = value;
                }
                if (input.type === 'date') {
                    input.max = today;
                }
            });
            part('month-label').textContent = view.monthLabel;
            part('month-next').disabled = !view.canGoNext;
            part('weekdays').replaceChildren.apply(part('weekdays'), view.weekdays.map(function (name) {
                return element('span', 'activity-range__weekday', name);
            }));
            days.replaceChildren.apply(days, view.days.map(function (day) {
                var button = element('button', 'activity-range__day', String(day.day));

                button.type = 'button';
                button.dataset.day = day.key;
                button.disabled = day.isFuture;
                button.setAttribute('aria-label', day.label);
                button.setAttribute('aria-pressed', String(day.isFrom || day.isTo));
                button.classList.toggle('activity-range__day--outside', !day.inMonth);
                button.classList.toggle('activity-range__day--today', day.isToday);
                button.classList.toggle('activity-range__day--edge', day.isFrom || day.isTo);
                button.classList.toggle('activity-range__day--between', day.inRange);

                return button;
            }));
            part('calendar-hint').textContent = view.hint;
        }

        /**
         * @param {boolean} returnFocus Whether the focus goes back to the button
         */
        function close(returnFocus) {
            draft = null;
            popover.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            toggle.classList.remove('activity-search__range-button--open');
            showError(null);
            if (returnFocus) {
                toggle.focus();
            }
        }

        /** Opens the popover on a draft of the range in use. */
        function open() {
            var active;

            draft = rangeDraft.open(current, zone);
            showError(null);
            draw();
            popover.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            toggle.classList.add('activity-search__range-button--open');
            active = root.querySelector('[data-role="preset"][aria-pressed="true"]');
            if (active) {
                active.focus();
            }
        }

        /**
         * The first of the candidates that can take the focus
         *
         * @param {Array<?HTMLElement>} candidates
         * @returns {?HTMLElement}
         */
        function focusable(candidates) {
            return candidates.filter(function (candidate) {
                return candidate !== null && !candidate.disabled;
            })[0] || null;
        }

        /**
         * @param {string} key A day "YYYY-MM-DD"
         * @returns {?HTMLElement} The day's button as drawn now
         */
        function dayButton(key) {
            return days.querySelector('button[data-day="' + key + '"]');
        }

        /**
         * Redraws the popover for an edited draft, keeping the focus in it so Esc and the keyboard go on working: on
         * the day given (a day just picked), else on the day that had the focus, drawn anew; a control that had the
         * focus and is gone or disabled hands it to the day picked or to the previous month's button
         *
         * @param {Object} next
         * @param {?string=} pickedDay The day the admin picked, which keeps the focus
         */
        function edit(next, pickedDay) {
            var had = doc.activeElement,
                inside = Boolean(had) && popover.contains(had),
                before = inside && had.dataset && had.dataset.day ? had.dataset.day : null,
                target;

            if (next === draft) {
                return;
            }
            draft = next;
            showError(null);
            draw();
            if (pickedDay || before !== null) {
                target = focusable([dayButton(pickedDay || before),
                    days.querySelector('button[data-day][aria-pressed="true"]'), part('month-prev')]);
            } else if (inside && (had.disabled || !popover.contains(had))) {
                target = focusable([days.querySelector('button[data-day][aria-pressed="true"]'), part('month-prev'),
                    part('range-cancel')]);
            }
            if (target) {
                target.focus();
            }
        }

        listen(toggle, 'click', function () {
            if (draft === null) {
                open();
            } else {
                close(false);
            }
        });
        listen(popover, 'click', function (event) {
            var preset = event.target.closest('[data-role="preset"]'),
                day = event.target.closest('button[data-day]');

            if (preset) {
                edit(rangeDraft.choosePreset(draft, preset.dataset.preset, options.now(), zone));

                return;
            }
            if (day && !day.disabled) {
                edit(rangeDraft.pickDay(draft, day.dataset.day, rangeDraft.dayKey(options.now(), zone)),
                    day.dataset.day);
            }
        });
        listen(popover, 'change', function (event) {
            if (event.target.dataset && event.target.dataset.input) {
                edit(rangeDraft.type(draft, event.target.dataset.input, event.target.value));
            }
        });
        listen(part('month-prev'), 'click', function () {
            edit(rangeDraft.shiftMonth(draft, -1));
        });
        listen(part('month-next'), 'click', function () {
            edit(rangeDraft.shiftMonth(draft, 1));
        });
        listen(part('range-cancel'), 'click', function () {
            close(true);
        });
        listen(part('range-apply'), 'click', function () {
            var applied = rangeDraft.apply(draft, options.now(), zone);

            if (!applied.ok) {
                showError(applied.error);

                return;
            }
            close(true);
            options.onApply(applied.range);
        });
        listen(doc, 'keydown', function (event) {
            var target = event.target;

            if (event.key === 'Escape' && draft !== null
                && (target === doc.body || !target || root.contains(target))) {
                event.preventDefault();
                close(true);
            }
        });
        listen(doc, 'mousedown', function (event) {
            if (draft !== null && !root.contains(event.target)) {
                close(false);
            }
        });

        return Object.freeze({
            /**
             * @param {{preset: string, from: number, to: number}} next The range in use, brought up to date
             */
            render: function (next) {
                var described = range.describe(next, zone, words);

                current = next;
                part('range-label').textContent = described.label;
                part('range-detail').textContent = described.from + ' – ' + described.to;
            }
        });
    }

    return Object.freeze({create: create});
});
