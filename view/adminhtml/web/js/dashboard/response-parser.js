/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Checks the dashboard data the server sends and the config the page prints, so the rest of the script works on
 * known shapes. Pure: throws a DashboardShapeError on anything else.
 */
define([], function () {
    'use strict';

    /**
     * @param {string} message
     * @constructor
     */
    function DashboardShapeError(message) {
        this.name = 'DashboardShapeError';
        this.message = message;
    }

    DashboardShapeError.prototype = Object.create(Error.prototype);

    /**
     * @param {*} value
     * @returns {boolean}
     */
    function isObject(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }

    /**
     * @param {*} value
     * @param {string} what
     * @returns {Array<string>}
     */
    function strings(value, what) {
        if (!Array.isArray(value) || !value.every(function (item) {
            return typeof item === 'string';
        })) {
            throw new DashboardShapeError(what + ' is not a list of strings');
        }

        return value;
    }

    /**
     * @param {*} card
     * @returns {Object}
     */
    function summaryCard(card) {
        var fields = ['key', 'label', 'value', 'unit', 'delta', 'direction', 'tone'];

        if (!isObject(card) || !fields.every(function (field) {
            return typeof card[field] === 'string';
        }) || typeof card.empty !== 'boolean') {
            throw new DashboardShapeError('a summary card is malformed');
        }

        return card;
    }

    /**
     * @param {*} raw The decoded JSON body
     * @returns {{periodDays: number, channels: Array<string>, summary: Array<Object>, widgets: Object}}
     */
    function parseData(raw) {
        if (!isObject(raw) || typeof raw.periodDays !== 'number' || !Array.isArray(raw.summary) ||
            !isObject(raw.widgets)) {
            throw new DashboardShapeError('the dashboard data is malformed');
        }

        Object.keys(raw.widgets).forEach(function (code) {
            var widget = raw.widgets[code];

            if (!isObject(widget) || (widget.status !== 'ok' && widget.status !== 'error') ||
                (widget.status === 'ok' && !isObject(widget.data))) {
                throw new DashboardShapeError('widget "' + code + '" is malformed');
            }
        });

        return {
            periodDays: raw.periodDays,
            channels: strings(raw.channels, 'channels'),
            summary: raw.summary.map(summaryCard),
            widgets: raw.widgets
        };
    }

    /**
     * @param {*} raw The decoded JSON the page printed
     * @returns {Object}
     */
    function parseConfig(raw) {
        var urls = ['dataUrl', 'preferenceUrl', 'formKey'];

        if (!isObject(raw) || !urls.every(function (field) {
            return typeof raw[field] === 'string' && raw[field] !== '';
        }) || typeof raw.periodDays !== 'number' || !isObject(raw.widgets) || !isObject(raw.wording)) {
            throw new DashboardShapeError('the dashboard config is malformed');
        }

        strings(raw.channels, 'channels');

        return raw;
    }

    return Object.freeze({
        parseData: parseData,
        parseConfig: parseConfig,
        DashboardShapeError: DashboardShapeError
    });
});
