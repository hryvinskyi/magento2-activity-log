/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * One activity of the timeline drawn from its sentence model (search/sentence.js): the time with its milliseconds and
 * the channel's badge, the sentence "actor via [channel] client verb what on subject-type subject (scope): before →
 * after" (the channel a client acted through is a small badge: the registered channel's name and colours, or its
 * code in capitals on a neutral badge when no module registers it), a row per field (inline values, a word diff of long
 * text clipped to three lines, the items a JSON list gained and lost, a masked secret; a field the system set by
 * itself carries a small "set automatically" tag), the fields recorded unchanged (a control that opens their rows,
 * saying how many of them it shows when the result carries only some, or only their number when no rows were
 * recorded), the request's other
 * activities (saying so when the list stops before the last of them), the request's chip and the link to the
 * details. A change in which every changed field was set automatically reads "Magento updated …", and the actor whose
 * save it happened in moves to the line under the sentence. Renders with template clones, created elements and
 * textContent only; links come from the parsed results, which accept only http(s) addresses and site paths.
 *
 * The item only draws. Its controls carry data-action names ("diff", "same", "unchanged", "siblings", "actor",
 * "request") that the timeline answers; the expanding ones are answered here (toggle), the rest by the timeline.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/wording'
], function (wording) {
    'use strict';

    var SUCCESS = 'success',
        SHORT_ID = 8;

    /**
     * @param {Object} assets As timeline/assets.read returns them
     * @param {{zone: Object, formatNumber: function(number): string, formatDuration: function(number): string,
     *     relative: function(number): string, searchLink: ?function(Array<Object>): ?string, asks: boolean}} context
     *     searchLink gives the address of the log searching for some chips (null: no such links); asks tells whether
     *     the actor and the request filter the shown search in place
     * @returns {{render: function(Object): HTMLElement, modelOf: function(HTMLElement): ?Object,
     *     toggle: function(string, HTMLElement): boolean, siblings: function(HTMLElement, Array<Object>, boolean=)}}
     */
    function create(assets, context) {
        var config = assets.config,
            words = config.wording,
            models = new WeakMap(),
            VALUES,
            TOGGLES;

        /**
         * @param {HTMLElement} within
         * @param {string} tag
         * @param {string} className
         * @param {string=} text
         * @returns {HTMLElement}
         */
        function element(within, tag, className, text) {
            var node = within.ownerDocument.createElement(tag);

            node.className = className;
            if (text !== undefined) {
                node.textContent = text;
            }

            return node;
        }

        /**
         * @param {HTMLElement} node
         * @param {string} name
         * @returns {HTMLElement}
         */
        function slot(node, name) {
            return node.querySelector('[data-slot="' + name + '"]');
        }

        /**
         * @param {HTMLElement} badge
         * @param {string} code
         */
        function paintChannel(badge, code) {
            var channel = config.channels[code];

            badge.textContent = channel === undefined ? code : channel.label;
            if (channel !== undefined) {
                badge.style.backgroundColor = channel.bg;
                badge.style.color = channel.fg;
            }
        }

        /**
         * The badge of the channel a client acted through
         *
         * @param {HTMLElement} within
         * @param {string} code
         * @returns {HTMLElement}
         */
        function viaBadge(within, code) {
            var channel = config.channels[code],
                badge = element(within, 'span', 'activity-stream__via'
                    + (channel === undefined ? ' activity-stream__via--unknown' : ''));

            badge.textContent = channel === undefined ? code.toUpperCase() : channel.label;
            if (channel !== undefined) {
                badge.style.backgroundColor = channel.bg;
                badge.style.color = channel.fg;
            }

            return badge;
        }

        /**
         * @param {HTMLElement} within
         * @param {string} outcome
         * @returns {HTMLElement}
         */
        function outcomeBadge(within, outcome) {
            var badge = element(within, 'span', 'activity-outcome activity-outcome--' + outcome);

            badge.appendChild(element(within, 'span', 'activity-outcome__icon'));
            badge.appendChild(element(within, 'span', '', config.outcomes[outcome]));

            return badge;
        }

        /**
         * A link to the log searching for one value: an <a> when the page has such links, marked to filter in place
         * when the page filters in place; a plain element otherwise
         *
         * @param {HTMLElement} within
         * @param {string} action "actor" or "request"
         * @param {{key: string, value: string}} chip
         * @param {string} className
         * @param {string} text
         * @returns {HTMLElement}
         */
        function filterLink(within, action, chip, className, text) {
            var href = context.searchLink === null ? null : context.searchLink([chip]),
                node;

            if (href === null && !context.asks) {
                return element(within, 'span', className, text);
            }
            node = element(within, href === null ? 'button' : 'a', className, text);
            if (href === null) {
                node.setAttribute('type', 'button');
            } else {
                node.setAttribute('href', href);
            }
            if (context.asks) {
                node.setAttribute('data-action', action);
                node.setAttribute('data-value', chip.value);
            }

            return node;
        }

        /**
         * @param {HTMLElement} within
         * @param {?string} url
         * @param {string} className
         * @param {string} text
         * @returns {HTMLElement}
         */
        function link(within, url, className, text) {
            var node = element(within, url === null ? 'strong' : 'a', className, text);

            if (url !== null) {
                node.setAttribute('href', url);
            }

            return node;
        }

        /**
         * @param {HTMLElement} within
         * @param {{text: string, empty: boolean}} value
         * @param {string} className
         * @returns {HTMLElement}
         */
        function valueSpan(within, value, className) {
            return element(
                within,
                'span',
                className + (value.empty ? ' activity-stream__value--empty' : ''),
                value.empty ? words.empty : value.text
            );
        }

        /**
         * "before → after" into a node
         *
         * @param {HTMLElement} into
         * @param {{text: string, empty: boolean}} before
         * @param {{text: string, empty: boolean}} after
         */
        function beforeAfter(into, before, after) {
            into.appendChild(valueSpan(into, before, 'activity-stream__before'));
            into.appendChild(element(into, 'span', 'activity-stream__arrow', ' → '));
            into.appendChild(valueSpan(into, after, 'activity-stream__after'));
        }

        /**
         * @param {HTMLElement} within
         * @param {string} action
         * @param {string} label
         * @returns {HTMLElement}
         */
        function toggleButton(within, action, label) {
            var button = element(within, 'button', 'activity-stream__link', label);

            button.setAttribute('type', 'button');
            button.setAttribute('data-action', action);
            button.setAttribute('aria-expanded', 'false');

            return button;
        }

        /**
         * A long text's word diff, the parts the diff compared marked deleted or inserted; what lies beyond the
         * compared words is shown as it was and as it is
         *
         * @param {HTMLElement} within
         * @param {{parts: Array<{op: string, text: string}>, rest: {before: string, after: string}}} diff
         * @returns {HTMLElement}
         */
        function wordDiff(within, diff) {
            var block = element(within, 'div', 'activity-stream__long'),
                classes = {equal: '', 'delete': 'activity-stream__del', insert: 'activity-stream__ins'};

            block.setAttribute('data-part', 'long');
            diff.parts.forEach(function (part) {
                block.appendChild(element(within, 'span', classes[part.op], part.text));
            });
            if (diff.rest.before === diff.rest.after) {
                block.appendChild(element(within, 'span', '', diff.rest.after));
            } else {
                block.appendChild(element(within, 'span', classes['delete'], diff.rest.before));
                block.appendChild(element(within, 'span', classes.insert, diff.rest.after));
            }

            return block;
        }

        /**
         * How each kind of value is drawn into a row's value cell; every view the sentence model gives has one
         */
        VALUES = Object.freeze({
            short: function (cell, row) {
                beforeAfter(cell, row.before, row.after);
            },
            long: function (cell, row) {
                var meta = element(cell, 'div', 'activity-stream__value-meta');

                cell.appendChild(wordDiff(cell, row.view.diff));
                meta.appendChild(toggleButton(cell, 'diff', words.showFullDiff));
                meta.appendChild(element(cell, 'span', '', wording.fill(
                    words.longMeta,
                    context.formatNumber(row.beforeLength),
                    context.formatNumber(row.afterLength),
                    context.formatNumber(row.view.diff.edits)
                )));
                if (row.truncated) {
                    meta.appendChild(element(cell, 'span', '', words.truncated));
                }
                cell.appendChild(meta);
            },
            json: function (cell, row) {
                var list = element(cell, 'div', 'activity-stream__json'),
                    meta = element(cell, 'div', 'activity-stream__value-meta'),
                    line = function (text, className, sign) {
                        return element(cell, 'div', className, sign + ' ' + text);
                    };

                list.setAttribute('data-part', 'json');
                row.view.removed.forEach(function (item) {
                    list.appendChild(line(item, 'activity-stream__json-removed', '−'));
                });
                row.view.added.forEach(function (item) {
                    list.appendChild(line(item, 'activity-stream__json-added', '+'));
                });
                row.view.same.forEach(function (item) {
                    var same = line(item, 'activity-stream__json-same', ' ');

                    same.setAttribute('data-part', 'same');
                    same.hidden = true;
                    list.appendChild(same);
                });
                cell.appendChild(list);
                meta.appendChild(element(cell, 'span', '', wording.fill(
                    words.jsonMeta,
                    context.formatNumber(row.view.added.length),
                    context.formatNumber(row.view.removed.length),
                    context.formatNumber(row.view.same.length)
                )));
                if (row.view.same.length > 0) {
                    meta.appendChild(toggleButton(cell, 'same', words.showUnchanged));
                }
                cell.appendChild(meta);
            },
            masked: function (cell) {
                var masked = assets.clone('masked');

                slot(masked, 'tag').textContent = words.masked;
                slot(masked, 'note').textContent = words.maskedNote;
                cell.appendChild(masked);
            }
        });

        /**
         * @param {Object} row A row of the sentence model
         * @param {boolean} unchanged Whether it is a field recorded unchanged
         * @returns {HTMLElement}
         */
        function fieldRow(row, unchanged) {
            var node = assets.clone('row'),
                name = slot(node, 'name'),
                scope = slot(node, 'scope'),
                automatic = slot(node, 'automatic');

            if (row.highlighted) {
                node.classList.add('activity-stream__row--highlighted');
            }
            if (unchanged) {
                node.setAttribute('data-unchanged', '');
            }
            slot(node, 'label').textContent = row.label;
            name.textContent = row.name;
            name.hidden = !row.showName;
            scope.textContent = row.scope === null ? '' : row.scope;
            scope.hidden = row.scope === null;
            automatic.textContent = row.automatic ? words.automatic : '';
            automatic.hidden = !row.automatic;
            if (row.automatic) {
                node.setAttribute('data-automatic', '');
            }
            VALUES[row.view.type](slot(node, 'value'), row);

            return node;
        }

        /**
         * Who acted, and through which client and channel, into a node
         *
         * @param {HTMLElement} into
         * @param {Object} model
         */
        function actorOf(into, model) {
            into.appendChild(filterLink(into, 'actor', {key: 'by', value: model.actor.name}, 'activity-stream__actor',
                model.actor.name));
            if (model.actor.via !== null || model.actor.viaChannel !== null) {
                into.appendChild(element(into, 'span', 'activity-stream__muted', ' ' + words.via + ' '));
            }
            if (model.actor.viaChannel !== null) {
                into.appendChild(viaBadge(into, model.actor.viaChannel));
                into.appendChild(element(into, 'span', '', model.actor.via === null ? '' : ' '));
            }
            if (model.actor.via !== null) {
                into.appendChild(element(into, 'strong', '', model.actor.via));
            }
        }

        /**
         * "during a save by actor · " before the kind of actor, for a change the system made by itself: the sentence
         * names the system, so the actor whose save it happened in is named here
         *
         * @param {HTMLElement} meta The kind of actor's slot, its text already set
         * @param {Object} model
         */
        function automaticActorOf(meta, model) {
            var kind = element(meta, 'span', '', meta.textContent);

            meta.textContent = '';
            meta.appendChild(element(meta, 'span', 'activity-stream__muted', words.automaticDuring + ' '));
            actorOf(meta, model);
            if (kind.textContent !== '') {
                meta.appendChild(element(meta, 'span', '', ' · '));
                meta.appendChild(kind);
            }
        }

        /**
         * The sentence's parts, in reading order
         *
         * @param {HTMLElement} sentence
         * @param {Object} model
         */
        function sentenceOf(sentence, model) {
            var add = function (node) {
                    sentence.appendChild(node);
                },
                say = function (text, className) {
                    add(element(sentence, 'span', className || '', text));
                },
                what = model.what;

            if (model.automatic) {
                add(element(sentence, 'span', 'activity-stream__system', words.automaticUpdated));
            } else {
                actorOf(sentence, model);
                say(' ' + config.verbs[model.verb]);
            }
            if (what !== null) {
                say(' ');
                add(element(sentence, 'strong', '', what.type === 'fields'
                    ? wording.fill(words.fields, context.formatNumber(what.count))
                    : what.label));
            }
            if (model.subject.label !== null) {
                say(what === null ? ' ' : ' ' + words.on + ' ');
                if (model.subject.typeLabel !== null) {
                    say(model.subject.typeLabel + ' ', 'activity-stream__muted');
                }
                add(link(sentence, model.subject.url === null ? model.detailUrl : model.subject.url,
                    'activity-stream__subject', model.subject.label));
            }
            if (model.scope !== null) {
                say(' (' + (model.scope.type === 'one' ? model.scope.label : words.severalScopes) + ')',
                    'activity-stream__muted');
            }
            if (model.inline !== null) {
                say(': ');
                beforeAfter(sentence, model.inline.before, model.inline.after);
            }
            if (model.createdWith !== null) {
                say(' ' + wording.count(model.createdWith, words.createdWithOne, words.createdWith,
                    context.formatNumber), 'activity-stream__muted');
            }
            if (model.outcome !== SUCCESS) {
                say(' ');
                add(outcomeBadge(sentence, model.outcome));
                if (model.message !== null && model.message !== '') {
                    say(' — ' + model.message, 'activity-stream__muted');
                }
            }
        }

        /**
         * @param {HTMLElement} rows
         * @param {Object} model
         */
        function rowsOf(rows, model) {
            var more;

            model.rows.forEach(function (row) {
                rows.appendChild(fieldRow(row, false));
            });
            if (model.moreFieldCount > 0) {
                more = element(rows, 'div', 'activity-stream__more-fields',
                    wording.fill(words.moreFields, context.formatNumber(model.moreFieldCount)) + ' ');
                more.setAttribute('data-part', 'more-fields');
                more.appendChild(link(rows, model.subject.url === null ? model.detailUrl : model.subject.url, '',
                    words.openRecord));
                rows.appendChild(more);
            }
            rows.hidden = rows.children.length === 0;
        }

        /**
         * The line under the sentence: kind of actor and IP, the unchanged fields, the request's other activities,
         * the request's chip and the details link
         *
         * @param {HTMLElement} node The item
         * @param {Object} model
         */
        function metaOf(node, model) {
            var unchanged = slot(node, 'unchanged'),
                unchangedCount = slot(node, 'unchanged-count'),
                siblings = slot(node, 'siblings'),
                correlation = slot(node, 'correlation'),
                details = slot(node, 'details'),
                actorType = slot(node, 'actor-type'),
                type = config.actorTypes[model.actor.type];

            actorType.textContent = [type === undefined ? model.actor.type : type, model.ip]
                .filter(function (part) {
                    return part !== null && part !== '';
                }).join(' · ');
            if (model.automatic) {
                automaticActorOf(actorType, model);
            }
            unchanged.hidden = model.unchanged.count === 0 || !model.unchanged.recorded;
            unchanged.setAttribute('data-action', 'unchanged');
            unchanged.setAttribute('aria-expanded', 'false');
            unchanged.textContent = wording.count(model.unchanged.count, words.unchangedOne, words.unchangedMany,
                context.formatNumber);
            unchangedCount.hidden = model.unchanged.count === 0 || model.unchanged.recorded;
            unchangedCount.textContent = wording.count(model.unchanged.count, words.unchangedCountOne,
                words.unchangedCountMany, context.formatNumber);
            siblings.hidden = model.siblingCount === 0;
            siblings.setAttribute('data-action', 'siblings');
            slot(node, 'siblings-label').textContent = wording.fill(words.siblings,
                context.formatNumber(model.siblingCount));
            correlation.appendChild(filterLink(correlation, 'request', {key: 'request', value: model.correlationId},
                'activity-stream__correlation', model.correlationId.slice(0, SHORT_ID)));
            correlation.setAttribute('title', words.correlationTitle + ': ' + model.correlationId);
            details.setAttribute('href', model.detailUrl);
            details.textContent = words.openDetails;
        }

        /**
         * One activity
         *
         * @param {Object} model As search/sentence.build returns it
         * @returns {HTMLElement}
         */
        function render(model) {
            var node = assets.clone('item'),
                wall = context.zone.wallTime(model.occurredAtMs);

            models.set(node, model);
            node.setAttribute('tabindex', '-1');
            node.setAttribute('data-activity', String(model.id));
            slot(node, 'when').setAttribute('title', context.relative(model.occurredAtMs));
            slot(node, 'clock').textContent = context.zone.format(model.occurredAtMs, 'clock');
            slot(node, 'ms').textContent = '.' + String(wall.millisecond).padStart(3, '0');
            paintChannel(slot(node, 'channel'), model.channel);
            sentenceOf(slot(node, 'sentence'), model);
            rowsOf(slot(node, 'rows'), model);
            metaOf(node, model);

            return node;
        }

        /**
         * @param {HTMLElement} button
         * @param {boolean} open
         * @param {string} openLabel
         * @param {string} closedLabel
         */
        function mark(button, open, openLabel, closedLabel) {
            button.setAttribute('aria-expanded', String(open));
            button.textContent = open ? openLabel : closedLabel;
        }

        /**
         * The expanding controls an item answers itself, by data-action
         */
        TOGGLES = Object.freeze({
            diff: function (button) {
                var block = button.closest('[data-slot="value"]').querySelector('[data-part="long"]'),
                    open = button.getAttribute('aria-expanded') !== 'true';

                block.classList.toggle('activity-stream__long--full', open);
                mark(button, open, words.collapse, words.showFullDiff);
            },
            same: function (button) {
                var open = button.getAttribute('aria-expanded') !== 'true';

                button.closest('[data-slot="value"]').querySelectorAll('[data-part="same"]').forEach(function (line) {
                    line.hidden = !open;
                });
                mark(button, open, words.hideUnchanged, words.showUnchanged);
            },
            unchanged: function (button) {
                var node = button.closest('[data-activity]'),
                    model = models.get(node),
                    rows = slot(node, 'rows'),
                    open = button.getAttribute('aria-expanded') !== 'true';

                rows.querySelectorAll('[data-unchanged]').forEach(function (row) {
                    row.remove();
                });
                if (open) {
                    model.unchanged.rows.forEach(function (row) {
                        rows.appendChild(fieldRow(row, true));
                    });
                    if (model.unchanged.partial) {
                        rows.appendChild(partialNote(rows, model));
                    }
                }
                rows.hidden = rows.children.length === 0;
                mark(button, open, words.hideUnchanged, wording.count(model.unchanged.count, words.unchangedOne,
                    words.unchangedMany, context.formatNumber));
            }
        });

        /**
         * The line under the unchanged rows when the result carries only some of them: how many, and where the rest are
         *
         * @param {HTMLElement} within
         * @param {Object} model
         * @returns {HTMLElement}
         */
        function partialNote(within, model) {
            var note = element(within, 'div', 'activity-stream__more-fields', wording.fill(
                words.unchangedPartial,
                context.formatNumber(model.unchanged.rows.length),
                context.formatNumber(model.unchanged.count)
            ) + ' ');

            note.setAttribute('data-unchanged', '');
            note.setAttribute('data-part', 'unchanged-partial');
            note.appendChild(link(within, model.detailUrl, '', words.openDetails));

            return note;
        }

        /**
         * Answers an expanding control of an item
         *
         * @param {string} action
         * @param {HTMLElement} button
         * @returns {boolean} Whether the action was one the item answers
         */
        function toggle(action, button) {
            if (!Object.prototype.hasOwnProperty.call(TOGGLES, action)) {
                return false;
            }
            TOGGLES[action](button);

            return true;
        }

        /**
         * The request's other activities under an item
         *
         * @param {HTMLElement} list The item's sibling list
         * @param {Array<Object>} siblings As search/sentence.sibling returns them
         * @param {boolean=} more Whether the request has more activities than the list holds
         */
        function siblings(list, siblings, more) {
            var capped;

            list.replaceChildren();
            siblings.forEach(function (sibling) {
                var line = assets.clone('sibling'),
                    wall = context.zone.wallTime(sibling.occurredAtMs),
                    action = slot(line, 'action'),
                    meta = [sibling.durationMs === null ? null : context.formatDuration(sibling.durationMs),
                        sibling.fieldLabels.join(', ')];

                slot(line, 'clock').textContent = context.zone.format(sibling.occurredAtMs, 'clock') + '.'
                    + String(wall.millisecond).padStart(3, '0');
                paintChannel(slot(line, 'channel'), sibling.channel);
                action.textContent = sibling.subjectLabel === null
                    ? sibling.action
                    : sibling.action + ' · ' + sibling.subjectLabel;
                action.setAttribute('href', sibling.detailUrl);
                slot(line, 'meta').textContent = meta.filter(function (part) {
                    return part !== null && part !== '';
                }).join(' · ');
                slot(line, 'outcome').appendChild(outcomeBadge(line, sibling.outcome));
                list.appendChild(line);
            });
            if (more === true) {
                capped = element(list, 'div', 'activity-stream__more-fields',
                    wording.fill(words.siblingsCapped, context.formatNumber(siblings.length)));
                capped.setAttribute('data-part', 'siblings-capped');
                list.appendChild(capped);
            }
        }

        return Object.freeze({
            render: render,
            modelOf: function (node) {
                return models.get(node) || null;
            },
            toggle: toggle,
            siblings: siblings
        });
    }

    return Object.freeze({create: create});
});
