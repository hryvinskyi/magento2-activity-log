/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The config the timeline's assets print (the layout handle "hryvinskyi_activitylog_timeline_assets"), checked and
 * copied into the timeline's own shape: its wording, the words of every verb, the channels with their badge colours,
 * the names of the kinds of actor and of the outcomes, and how many activities a text search looks through at most.
 *
 * A config missing a word the timeline writes, a verb or an outcome the search answers with, is refused with a
 * TimelineConfigError rather than rendered with holes. Pure.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/request'
], function (request) {
    'use strict';

    var WORDING = Object.freeze(['via', 'on', 'empty', 'masked', 'maskedNote', 'automatic', 'automaticUpdated',
        'automaticDuring', 'severalScopes', 'createdWithOne', 'createdWith',
        'fields', 'moreFields', 'openRecord', 'showFullDiff', 'collapse', 'longMeta', 'truncated', 'jsonMeta',
        'showUnchanged', 'hideUnchanged', 'unchangedOne', 'unchangedMany', 'unchangedCountOne', 'unchangedCountMany',
        'unchangedPartial', 'siblings', 'siblingsCapped', 'correlationTitle',
        'openDetails', 'dayEventsOne', 'dayEventsMany', 'dayToday', 'dayYesterday', 'changeOne', 'changeMany',
        'events', 'newestFirst', 'showingOf', 'atLeast', 'loadMore', 'loadedOf', 'loading', 'emptyLogTitle',
        'emptyLogText', 'noResultsTitle', 'outsideOne', 'outsideMany', 'noMatch', 'nothingInRange', 'widen',
        'removeLast', 'clearAll', 'narrow', 'loadError', 'retry']);

    /**
     * @param {string} message
     * @constructor
     */
    function TimelineConfigError(message) {
        this.name = 'TimelineConfigError';
        this.message = message;
    }

    TimelineConfigError.prototype = Object.create(Error.prototype);

    /**
     * @param {*} value
     * @param {string} what
     * @returns {Object}
     */
    function record(value, what) {
        if (value === null || typeof value !== 'object' || Array.isArray(value)) {
            throw new TimelineConfigError(what + ' is not an object');
        }

        return value;
    }

    /**
     * @param {*} value
     * @param {string} what
     * @returns {string}
     */
    function text(value, what) {
        if (typeof value !== 'string') {
            throw new TimelineConfigError(what + ' is not text');
        }

        return value;
    }

    /**
     * @param {*} raw
     * @param {Array<string>} names Names that must be present
     * @param {string} what
     * @returns {Object<string, string>} Every text of the map, the required ones checked
     */
    function texts(raw, names, what) {
        var copy = {};

        Object.keys(record(raw, what)).forEach(function (name) {
            copy[name] = text(raw[name], what + ' "' + name + '"');
        });
        names.forEach(function (name) {
            if (!Object.prototype.hasOwnProperty.call(copy, name)) {
                throw new TimelineConfigError(what + ' "' + name + '" is missing');
            }
        });

        return copy;
    }

    /**
     * @param {*} raw
     * @returns {Object<string, {label: string, bg: string, fg: string}>}
     */
    function channels(raw) {
        var copy = {};

        Object.keys(record(raw, 'the channels')).forEach(function (code) {
            var channel = record(raw[code], 'the channel "' + code + '"');

            copy[code] = {
                label: text(channel.label, 'a channel label'),
                bg: text(channel.bg, 'a channel colour'),
                fg: text(channel.fg, 'a channel colour')
            };
        });

        return copy;
    }

    /**
     * The timeline config
     *
     * @param {*} raw The decoded JSON the assets printed
     * @returns {{wording: Object<string, string>, verbs: Object<string, string>,
     *     channels: Object<string, {label: string, bg: string, fg: string}>, actorTypes: Object<string, string>,
     *     outcomes: Object<string, string>, candidateLimit: number}}
     * @throws {TimelineConfigError}
     */
    function parse(raw) {
        record(raw, 'the timeline config');
        if (typeof raw.candidateLimit !== 'number' || Math.floor(raw.candidateLimit) !== raw.candidateLimit
            || raw.candidateLimit < 1) {
            throw new TimelineConfigError('the candidate limit is not a whole number of 1 or more');
        }

        return {
            wording: texts(raw.wording, WORDING, 'the wording'),
            verbs: texts(raw.verbs, request.VERBS, 'the verb'),
            channels: channels(raw.channels),
            actorTypes: texts(raw.actorTypes, [], 'the kind of actor'),
            outcomes: texts(raw.outcomes, request.OUTCOMES, 'the outcome'),
            candidateLimit: raw.candidateLimit
        };
    }

    return Object.freeze({
        WORDING: WORDING,
        parse: parse,
        TimelineConfigError: TimelineConfigError
    });
});
