/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The config the search page prints for its script, checked and copied into the page's own shape
 *
 * A config of any other shape is refused with a PageConfigError rather than half-used. Endpoints must be http(s)
 * addresses or paths from the site root. Pure.
 */
define([
    'Hryvinskyi_ActivityLog/js/search/request'
], function (request) {
    'use strict';

    var LINK = /^(https?:\/\/|\/)/i,
        VIEWS = ['timeline', 'grid'],
        WORDING = ['placeholder', 'placeholderMore', 'removeChip', 'textKey', 'quoted', 'anywhere', 'unknownKey',
            'filterBy', 'matching', 'textGroup', 'typed', 'limit', 'tooLong', 'pickDates', 'calendarFrom',
            'calendarTo', 'fromDate', 'fromTime', 'toDate', 'toTime', 'filtersOne', 'filtersMany', 'overallOne',
            'overallMany'];

    /**
     * @param {string} message
     * @constructor
     */
    function PageConfigError(message) {
        this.name = 'PageConfigError';
        this.message = message;
    }

    PageConfigError.prototype = Object.create(Error.prototype);

    /**
     * @param {*} value
     * @param {string} what
     * @returns {Object}
     */
    function record(value, what) {
        if (value === null || typeof value !== 'object' || Array.isArray(value)) {
            throw new PageConfigError(what + ' is not an object');
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
            throw new PageConfigError(what + ' is not text');
        }

        return value;
    }

    /**
     * @param {*} value
     * @param {string} what
     * @returns {string}
     */
    function link(value, what) {
        if (!LINK.test(text(value, what))) {
            throw new PageConfigError(what + ' is neither an http(s) address nor a path');
        }

        return value;
    }

    /**
     * @param {*} value
     * @param {string} what
     * @returns {number}
     */
    function whole(value, what) {
        if (typeof value !== 'number' || Math.floor(value) !== value || value < 0) {
            throw new PageConfigError(what + ' is not a whole number of 0 or more');
        }

        return value;
    }

    /**
     * @param {*} value
     * @param {string} what
     * @returns {Array<*>}
     */
    function list(value, what) {
        if (!Array.isArray(value)) {
            throw new PageConfigError(what + ' is not a list');
        }

        return value;
    }

    /**
     * @param {*} raw
     * @param {string} what
     * @returns {Object<string, string>}
     */
    function textMap(raw, what) {
        var copy = {};

        Object.keys(record(raw, what)).forEach(function (name) {
            copy[name] = text(raw[name], what + ' "' + name + '"');
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
            var channel = record(raw[code], 'a channel');

            copy[code] = {
                label: text(channel.label, 'a channel label'),
                bg: text(channel.bg, 'a channel colour'),
                fg: text(channel.fg, 'a channel colour')
            };
        });

        return copy;
    }

    /**
     * @param {*} raw
     * @returns {{title: string, chips: Array<{key: string, value: string}>, preset: string}}
     */
    function example(raw) {
        record(raw, 'an example');

        return {
            title: text(raw.title, 'an example title'),
            chips: list(raw.chips, 'an example\'s filters').map(function (chip) {
                record(chip, 'an example filter');

                return {key: text(chip.key, 'an example key'), value: text(chip.value, 'an example value')};
            }),
            preset: text(raw.preset, 'an example range')
        };
    }

    /**
     * @param {*} raw
     * @returns {Object}
     */
    function wording(raw) {
        var copy;

        record(raw, 'the wording');
        copy = {presets: textMap(raw.presets, 'the preset names'), groups: textMap(raw.groups, 'the group titles')};
        WORDING.forEach(function (name) {
            copy[name] = text(raw[name], 'the wording "' + name + '"');
        });

        return copy;
    }

    /**
     * The page config
     *
     * @param {*} raw The decoded JSON the page printed
     * @returns {Object} {searchUrl, suggestUrl, preferenceUrl, formKey, timeZone, locale, serverNowMs, timeoutMs,
     *     keys, channels, changeChannels, preferences: {view, changesOnly}, examples, grid: {provider, paging,
     *     selections, exportButton, exportOption, param}, wording}
     * @throws {PageConfigError}
     * @throws {request.SearchShapeError} On malformed keys
     */
    function parse(raw) {
        var preferences,
            grid;

        record(raw, 'the page config');
        preferences = record(raw.preferences, 'the view choices');
        grid = record(raw.grid, 'the grid');
        if (VIEWS.indexOf(preferences.view) === -1 || typeof preferences.changesOnly !== 'boolean') {
            throw new PageConfigError('the view choices are malformed');
        }

        return {
            searchUrl: link(raw.searchUrl, 'the search address'),
            suggestUrl: link(raw.suggestUrl, 'the suggestion address'),
            preferenceUrl: link(raw.preferenceUrl, 'the preference address'),
            formKey: text(raw.formKey, 'the form key'),
            timeZone: text(raw.timeZone, 'the timezone'),
            locale: text(raw.locale, 'the locale'),
            serverNowMs: whole(raw.serverNowMs, 'the server time'),
            timeoutMs: whole(raw.timeoutMs, 'the timeout'),
            keys: request.parseSuggestions({keys: raw.keys, lists: {}}).keys,
            channels: channels(raw.channels),
            changeChannels: list(raw.changeChannels, 'the change channels').map(function (code) {
                return text(code, 'a change channel');
            }),
            preferences: {view: preferences.view, changesOnly: preferences.changesOnly},
            examples: list(raw.examples, 'the examples').map(example),
            grid: {
                provider: text(grid.provider, 'the grid provider'),
                paging: text(grid.paging, 'the grid pager'),
                selections: text(grid.selections, 'the grid selection'),
                exportButton: text(grid.exportButton, 'the grid export button'),
                exportOption: text(grid.exportOption, 'the grid export option'),
                param: text(grid.param, 'the grid parameter')
            },
            wording: wording(raw.wording)
        };
    }

    return Object.freeze({
        parse: parse,
        PageConfigError: PageConfigError
    });
});
