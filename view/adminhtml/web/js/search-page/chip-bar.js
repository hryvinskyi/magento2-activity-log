/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The search bar: the chips, the input and its list of offers, an ARIA combobox.
 *
 * The bar shows the chips it is given and reports the chips the admin asks for; the page decides. Typed text and
 * every offer chosen go through the grammar (suggest.choose), so a chip from the list obeys the same rules as one
 * typed. Keys: see search/combobox.js. Renders with textContent and created elements only.
 *
 * Markup it works on (data-role): "field", "chips", "input", "clear", "popup", "listbox", "message", and a
 * <template data-role="icon-remove"> holding the chip's remove icon.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/grammar',
    'Hryvinskyi_ActivityLog/js/search/suggest',
    'Hryvinskyi_ActivityLog/js/search/combobox',
    'Hryvinskyi_ActivityLog/js/search/offer-view'
], function (grammar, suggest, combobox, offerView) {
    'use strict';

    /**
     * @param {HTMLElement} root The bar
     * @param {{words: Object, keys: Array<Object>, idPrefix: string, formatNumber: function(number): string,
     *     onChange: function(Array<Object>), signal: AbortSignal}} options onChange receives the chips asked for
     * @returns {{render: function(Array<Object>), setVocabulary: function(Object), focus: function()}}
     */
    function create(root, options) {
        var doc = root.ownerDocument,
            words = options.words,
            knownKeys = options.keys.map(function (description) {
                return description.key;
            }),
            part = function (role) {
                return root.querySelector('[data-role="' + role + '"]');
            },
            field = part('field'),
            chipList = part('chips'),
            input = part('input'),
            clear = part('clear'),
            popup = part('popup'),
            listbox = part('listbox'),
            message = part('message'),
            removeIcon = part('icon-remove'),
            listen = function (target, type, handler) {
                target.addEventListener(type, handler, {signal: options.signal});
            },
            vocabulary = {keys: options.keys, lists: {}},
            chips = [],
            state = combobox.initial(),
            items = [];

        /**
         * @param {string} tag
         * @param {string} className
         * @param {string=} text
         * @returns {HTMLElement}
         */
        function element(tag, className, text) {
            var node = doc.createElement(tag);

            node.className = className;
            if (text !== undefined) {
                node.textContent = text;
            }

            return node;
        }

        /**
         * @param {?string} code An error code of the grammar, or null to hide the message
         */
        function say(code) {
            message.textContent = offerView.refusal(code, words);
            message.hidden = message.textContent === '';
        }

        /**
         * @param {number} index
         * @returns {string}
         */
        function optionId(index) {
            return options.idPrefix + '-option-' + index;
        }

        /** Draws the list of offers, or hides it. */
        function drawList() {
            var groups = state.open ? suggest.suggest(state.input, chips, vocabulary) : [],
                read = offerView.offers(groups, words, options.keys, options.formatNumber);

            items = suggest.flatten(groups);
            listbox.replaceChildren();
            read.forEach(function (group, position) {
                var box = element('div', 'activity-search__group'),
                    title = element('div', 'activity-search__group-title', group.title);

                title.id = options.idPrefix + '-group-' + position;
                box.setAttribute('role', 'group');
                box.setAttribute('aria-labelledby', title.id);
                box.appendChild(title);
                group.items.forEach(function (line) {
                    var option = element('div', 'activity-search__option');

                    option.id = optionId(line.index);
                    option.dataset.index = String(line.index);
                    option.setAttribute('role', 'option');
                    option.setAttribute('aria-selected', String(line.index === state.active));
                    option.appendChild(element('span', 'activity-search__option-key', line.keyLabel));
                    option.appendChild(element(
                        'span',
                        'activity-search__option-text' + (line.mono ? ' activity-search__mono' : ''),
                        line.text
                    ));
                    option.appendChild(element('span', 'activity-search__option-hint', line.hint));
                    box.appendChild(option);
                });
                listbox.appendChild(box);
            });

            popup.hidden = items.length === 0;
            input.setAttribute('aria-expanded', String(!popup.hidden));
            if (!popup.hidden && state.active >= 0 && state.active < items.length) {
                input.setAttribute('aria-activedescendant', optionId(state.active));
                scrollToActive();
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        }

        /** Keeps the active offer in sight. */
        function scrollToActive() {
            var active = doc.getElementById(optionId(state.active));

            if (active && typeof active.scrollIntoView === 'function') {
                active.scrollIntoView({block: 'nearest'});
            }
        }

        /** Draws the chips, the placeholder and the "Clear" button. */
        function drawChips() {
            chipList.replaceChildren();
            offerView.chips(chips, words, options.keys).forEach(function (chip, index) {
                var box = element('span', 'activity-search__chip' + (chip.plain ? ' activity-search__chip--text' : '')),
                    remove = element('button', 'activity-search__chip-remove');

                if (!chip.plain) {
                    box.appendChild(element('span', 'activity-search__chip-key', chip.keyLabel));
                }
                box.appendChild(element(
                    'span',
                    'activity-search__chip-value' + (chip.mono ? ' activity-search__mono' : ''),
                    chip.value
                ));
                box.title = chip.keyLabel + ' ' + chip.value;
                remove.type = 'button';
                remove.dataset.index = String(index);
                remove.setAttribute('aria-label', chip.removeLabel);
                if (removeIcon) {
                    remove.appendChild(removeIcon.content.cloneNode(true));
                }
                box.appendChild(remove);
                chipList.appendChild(box);
            });
            input.placeholder = chips.length === 0 ? words.placeholder : words.placeholderMore;
            clear.hidden = chips.length === 0 && input.value === '';
        }

        /**
         * @param {Array<Object>} next
         */
        function ask(next) {
            options.onChange(next);
        }

        /**
         * @param {{ok: boolean, chip: ?Object, error: ?string}} parsed
         */
        function addChip(parsed) {
            var added;

            if (!parsed.ok) {
                say(parsed.error);

                return;
            }
            added = grammar.add(chips, parsed.chip);
            if (!added.ok) {
                say(added.error);

                return;
            }
            input.value = '';
            state = combobox.cleared();
            say(null);
            drawList();
            if (added.added) {
                ask(added.chips);
            } else {
                drawChips();
            }
        }

        /**
         * @param {Object} item An offer, as suggest.js gives it
         */
        function choose(item) {
            var chosen = suggest.choose(item, knownKeys);

            if (chosen.type === 'continue') {
                input.value = chosen.input;
                state = combobox.typed(chosen.input);
                drawList();
                input.focus();

                return;
            }
            addChip(chosen.result);
            input.focus();
        }

        /**
         * What each intent of a key press does
         */
        var intents = Object.freeze({
            move: function (intent) {
                state = intent.state;
                drawList();
            },
            choose: function () {
                choose(items[state.active]);
            },
            commit: function () {
                addChip(grammar.parse(state.input, knownKeys));
            },
            removeLast: function () {
                ask(grammar.removeLast(chips));
            },
            close: function (intent) {
                state = intent.state;
                drawList();
            }
        });

        listen(input, 'input', function () {
            state = combobox.typed(input.value);
            say(null);
            clear.hidden = chips.length === 0 && input.value === '';
            drawList();
        });
        listen(input, 'focus', function () {
            state = combobox.opened(state);
            drawList();
        });
        listen(input, 'keydown', function (event) {
            var intent = combobox.keyDown(event.key, state, items.length, chips.length > 0);

            if (intent === null) {
                return;
            }
            event.preventDefault();
            intents[intent.type](intent);
        });
        listen(root, 'focusout', function (event) {
            if (event.relatedTarget && root.contains(event.relatedTarget)) {
                return;
            }
            state = combobox.closed(state);
            drawList();
        });
        // The input keeps the focus while an offer is clicked
        listen(popup, 'mousedown', function (event) {
            event.preventDefault();
        });
        listen(listbox, 'click', function (event) {
            var option = event.target.closest('[role="option"]');

            if (option) {
                choose(items[Number(option.dataset.index)]);
            }
        });
        listen(listbox, 'mousemove', function (event) {
            var option = event.target.closest('[role="option"]'),
                index = option ? Number(option.dataset.index) : -1;

            if (index !== -1 && index !== state.active) {
                state = combobox.hovered(state, index, items.length);
                listbox.querySelectorAll('[role="option"]').forEach(function (node) {
                    node.setAttribute('aria-selected', String(Number(node.dataset.index) === state.active));
                });
                input.setAttribute('aria-activedescendant', optionId(state.active));
            }
        });
        listen(chipList, 'click', function (event) {
            var remove = event.target.closest('button[data-index]');

            if (remove) {
                ask(grammar.remove(chips, Number(remove.dataset.index)));
                input.focus();
            }
        });
        listen(field, 'click', function (event) {
            if (!event.target.closest('button')) {
                input.focus();
            }
        });
        listen(clear, 'click', function () {
            input.value = '';
            state = combobox.cleared();
            say(null);
            drawList();
            ask([]);
            drawChips();
            input.focus();
        });

        return Object.freeze({
            /**
             * @param {Array<{key: string, value: string}>} next The chips to show
             */
            render: function (next) {
                chips = next.slice();
                drawChips();
                if (state.open) {
                    drawList();
                }
            },

            /**
             * @param {{keys: Array<Object>, lists: Object}} next The suggestion lists, once loaded
             */
            setVocabulary: function (next) {
                vocabulary = next;
                if (state.open) {
                    drawList();
                }
            },

            /** Puts the cursor in the input. */
            focus: function () {
                input.focus();
            }
        });
    }

    return Object.freeze({create: create});
});
