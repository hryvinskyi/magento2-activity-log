/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * One search result as the sentence and rows the timeline shows: "actor (via channel client) verb what on subject-type
 * subject (scope): before → after", then a row per changed field
 *
 * - The verb is a code; the page prints its words.
 * - "What" is the one changed field's label, a count of fields for a change of several, or the action of an activity
 *   no module describes further.
 * - Fields set automatically (the system changed them while carrying out what the actor asked, without being asked)
 *   are not counted with what the actor changed, and their rows say so. A change in which every changed field was set
 *   automatically reads as the system's doing ("automatic"), counting those fields instead.
 * - A change of exactly one short value shows it inline instead of as a row; a record created shows the number of
 *   fields it was created with.
 * - An empty value (none, or an empty string) is marked empty, for the page's "(empty)".
 * - Fields recorded unchanged (a snapshot of the whole record) are kept apart, collapsed until asked for. When only
 *   their number was kept, the number is all the result has: there are no rows to open. When the result carries
 *   fewer rows than were recorded, it says so (partial), for the page to point at the details.
 * - Each row carries the view of its kind: inline values, a word diff of long text, the items a JSON list gained and
 *   lost (long text again when a side is not a list), or nothing at all for a masked secret.
 * - A row is highlighted when a chip of a key that names fields matches it: the row's code contains the chip's value,
 *   or its label is the value, ignoring case.
 * Pure: takes a parsed result (request.js), returns data.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/diff'
], function (diff) {
    'use strict';

    var CREATED = 'created',
        CHANGED = 'changed',
        VIEWS;

    /**
     * @param {?string} value
     * @returns {{text: string, empty: boolean}}
     */
    function valueOf(value) {
        var text = value === null || value === undefined ? '' : value;

        return {text: text, empty: text === ''};
    }

    /**
     * @param {Object} field
     * @returns {Object}
     */
    function longView(field) {
        return {type: 'long', diff: diff.words(field.before, field.after)};
    }

    /**
     * How each kind of value is shown; every kind the server sends has an entry
     */
    VIEWS = Object.freeze({
        short: function () {
            return {type: 'short'};
        },
        long: longView,
        json: function (field) {
            var lists = diff.array(field.before, field.after);

            return lists.ok
                ? {type: 'json', added: lists.added, removed: lists.removed, same: lists.same}
                : longView(field);
        },
        masked: function () {
            return {type: 'masked'};
        }
    });

    /**
     * The values of the chips whose key names fields, to highlight the rows they match
     *
     * @param {Array<{key: string, value: string}>} chips
     * @param {Array<{key: string, matchesFieldNames: boolean}>} keys The key descriptions
     * @returns {Array<string>} Lowercase
     */
    function fieldTerms(chips, keys) {
        var naming = keys.filter(function (description) {
            return description.matchesFieldNames === true;
        }).map(function (description) {
            return description.key;
        });

        return chips.filter(function (chip) {
            return naming.indexOf(chip.key) !== -1;
        }).map(function (chip) {
            return chip.value.toLowerCase();
        });
    }

    /**
     * A row matches a term when its label is the term or its code contains it; a term in double quotes ("price")
     * names one code exactly, as such a search value does
     *
     * @param {Object} field
     * @param {Array<string>} terms Lowercase
     * @returns {boolean}
     */
    function isHighlighted(field, terms) {
        var name = field.name.toLowerCase(),
            label = field.label.toLowerCase();

        return terms.some(function (term) {
            if (term.length > 2 && term.charAt(0) === '"' && term.charAt(term.length - 1) === '"') {
                return name === term.slice(1, -1);
            }

            return name.indexOf(term) !== -1 || label === term;
        });
    }

    /**
     * @param {Array<string>} terms Lowercase
     * @returns {function(Object): Object}
     */
    function rowOf(terms) {
        return function (field) {
            return row(field, terms);
        };
    }

    /**
     * @param {Object} field A parsed field row
     * @param {Array<string>} terms Lowercase values that highlight a row
     * @returns {Object}
     */
    function row(field, terms) {
        var masked = field.kind === 'masked';

        return {
            name: field.name,
            label: field.label,
            showName: field.name !== field.label,
            scope: field.scope,
            kind: field.kind,
            changed: field.changed,
            automatic: field.automatic,
            truncated: field.truncated,
            beforeLength: field.beforeLength,
            afterLength: field.afterLength,
            highlighted: isHighlighted(field, terms),
            before: masked ? null : valueOf(field.before),
            after: masked ? null : valueOf(field.after),
            view: VIEWS[field.kind](field)
        };
    }

    /**
     * @param {Array<Object>} changed
     * @returns {?{type: string, label: ?string}} "one" scope, "several" scopes, or null when no row has one
     */
    function scopeOf(changed) {
        var scopes = [];

        changed.forEach(function (field) {
            if (field.scope !== null && scopes.indexOf(field.scope) === -1) {
                scopes.push(field.scope);
            }
        });

        if (scopes.length === 0) {
            return null;
        }

        return scopes.length === 1 ? {type: 'one', label: scopes[0]} : {type: 'several', label: null};
    }

    /**
     * The changed fields the sentence is about, and how many of them there are: those the actor asked for, or, when the
     * system set every changed field by itself, those
     *
     * @param {Object} event
     * @param {Array<Object>} changed
     * @returns {{automatic: boolean, rows: Array<Object>, count: number}}
     */
    function focusOf(event, changed) {
        var detail = event.detail,
            asked = changed.filter(function (field) {
                return !field.automatic;
            }),
            automatic = changed.length - asked.length,
            askedCount = detail === null
                ? asked.length
                : Math.max(detail.fieldCount - detail.automaticCount, asked.length),
            automaticCount = detail === null ? automatic : Math.max(detail.automaticCount, automatic),
            byItself = detail !== null && event.verb === CHANGED && askedCount === 0 && automaticCount > 0;

        return byItself
            ? {automatic: true, rows: changed, count: automaticCount}
            : {automatic: false, rows: asked, count: askedCount};
    }

    /**
     * @param {Object} event
     * @param {{automatic: boolean, rows: Array<Object>, count: number}} focus
     * @returns {?{type: string, label: ?string, count: ?number}}
     */
    function whatOf(event, focus) {
        var detail = event.detail;

        if (detail === null) {
            return event.action === '' ? null : {type: 'action', label: event.action, count: null};
        }
        if (detail.what !== null) {
            return {type: 'field', label: detail.what, count: null};
        }
        if (event.verb === CHANGED && focus.count > 1) {
            return {type: 'fields', label: null, count: focus.count};
        }

        return null;
    }

    /**
     * @param {Object} event
     * @param {{automatic: boolean, rows: Array<Object>, count: number}} focus
     * @returns {boolean} Whether the one value the sentence is about is shown inside it
     */
    function isInline(event, focus) {
        return event.verb !== CREATED && focus.count === 1 && focus.rows.length === 1 && focus.rows[0].kind === 'short'
            && event.detail.moreFieldCount === 0;
    }

    /**
     * The subject's name in the sentence: the recording module's, which leaves out the kind of subject the sentence
     * already names, or else the name the journal recorded
     *
     * @param {Object} event
     * @returns {?string}
     */
    function subjectLabelOf(event) {
        if (event.detail !== null && typeof event.detail.subjectLabel === 'string') {
            return event.detail.subjectLabel;
        }

        return event.subject === null ? null : event.subject.label;
    }

    /**
     * The fields recorded unchanged: how many, the rows the result carries, whether rows were recorded at all, and
     * whether the result carries fewer of them than were recorded
     *
     * @param {?Object} detail
     * @param {Array<Object>} unchanged The result's unchanged field rows
     * @param {function(Object): Object} toRow
     * @returns {{count: number, rows: Array<Object>, recorded: boolean, partial: boolean}}
     */
    function unchangedOf(detail, unchanged, toRow) {
        var count = Math.max(detail === null ? 0 : detail.unchangedCount, unchanged.length),
            recorded = unchanged.length > 0 || detail === null || detail.unchangedRecorded;

        return {
            count: count,
            rows: unchanged.map(toRow),
            recorded: recorded,
            partial: recorded && unchanged.length < count
        };
    }

    /**
     * The sentence and rows of one result
     *
     * @param {Object} event A result as request.parsePage returns it
     * @param {Array<string>=} terms Field terms (see fieldTerms) whose rows are highlighted
     * @returns {Object} {id, occurredAtMs, channel, outcome, denied, actor: {name, type, via, viaChannel}, verb,
     *     automatic: boolean (every changed field was set automatically: the system's doing, not the actor's),
     *     what: ?{type: "field"|"fields"|"action", label, count}, createdWith: ?number,
     *     subject: {typeLabel, label, url}, scope: ?{type: "one"|"several", label},
     *     inline: ?{before: {text, empty}, after: {text, empty}}, rows, unchanged: {count, rows, recorded, partial},
     *     moreFieldCount,
     *     siblingCount, correlationId, detailUrl, ip, durationMs, message}
     */
    function build(event, terms) {
        var detail = event.detail,
            toRow = rowOf(terms || []),
            fields = detail === null ? [] : detail.fields,
            changed = fields.filter(function (field) {
                return field.changed;
            }),
            unchanged = fields.filter(function (field) {
                return !field.changed;
            }),
            focus = focusOf(event, changed),
            inline = detail !== null && isInline(event, focus),
            via = [event.actor.viaClient, event.actor.viaVersion].filter(function (part) {
                return part !== null && part !== '';
            }).join(' ');

        return {
            id: event.id,
            occurredAtMs: event.occurredAtMs,
            channel: event.channel,
            outcome: event.outcome,
            denied: event.outcome === 'denied',
            actor: {
                name: event.actor.name,
                type: event.actor.type,
                via: via === '' ? null : via,
                viaChannel: event.actor.viaChannel === '' ? null : event.actor.viaChannel
            },
            verb: event.verb,
            automatic: focus.automatic,
            what: whatOf(event, focus),
            createdWith: detail !== null && event.verb === CREATED && detail.fieldCount > 0 ? detail.fieldCount : null,
            subject: {
                typeLabel: detail === null ? null : detail.subjectTypeLabel,
                label: subjectLabelOf(event),
                url: detail === null ? null : detail.subjectUrl
            },
            scope: scopeOf(changed),
            inline: inline ? {before: valueOf(focus.rows[0].before), after: valueOf(focus.rows[0].after)} : null,
            rows: (inline ? changed.filter(function (field) {
                return field !== focus.rows[0];
            }) : changed).map(toRow),
            unchanged: unchangedOf(detail, unchanged, toRow),
            moreFieldCount: detail === null ? 0 : detail.moreFieldCount,
            siblingCount: event.siblingCount,
            correlationId: event.correlationId,
            detailUrl: event.detailUrl,
            ip: event.ip,
            durationMs: event.durationMs,
            message: event.message
        };
    }

    /**
     * One line of "N more in this request": when, which channel, what it did and to what, how long, which fields
     *
     * @param {Object} event A result as request.parsePage returns it
     * @returns {{id: number, occurredAtMs: number, channel: string, action: string, subjectLabel: ?string,
     *     durationMs: ?number, fieldLabels: Array<string>, outcome: string, detailUrl: string}}
     */
    function sibling(event) {
        return {
            id: event.id,
            occurredAtMs: event.occurredAtMs,
            channel: event.channel,
            action: event.action,
            subjectLabel: event.subject === null ? null : event.subject.label,
            durationMs: event.durationMs,
            fieldLabels: event.detail === null ? [] : event.detail.fields.filter(function (field) {
                return field.changed;
            }).map(function (field) {
                return field.label;
            }),
            outcome: event.outcome,
            detailUrl: event.detailUrl
        };
    }

    return Object.freeze({
        fieldTerms: fieldTerms,
        build: build,
        sibling: sibling
    });
});
