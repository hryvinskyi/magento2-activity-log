/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * What the layout handle "hryvinskyi_activitylog_timeline_assets" printed into the page, read once: the timeline's
 * config (timeline/config.js) and the element templates the timeline clones.
 *
 * Public surface, for any page that shows the timeline: read(document) returns {config, clone(name)}; clone gives a
 * fresh copy of the first element of <template data-template="name">. The page must include the handle; a page
 * without it, or with a template missing, gets a TimelineAssetsError instead of a timeline drawn with holes.
 */
define([
    'Hryvinskyi_ActivityLog/js/timeline/config'
], function (timelineConfig) {
    'use strict';

    var ROOT = '[data-role="activity-timeline-assets"]',
        CONFIG = 'script[data-role="activity-timeline-config"]',
        TEMPLATES = Object.freeze(['results', 'day', 'item', 'row', 'masked', 'sibling', 'skeleton', 'state',
            'empty-log', 'example']);

    /**
     * @param {string} message
     * @param {*=} cause
     * @constructor
     */
    function TimelineAssetsError(message, cause) {
        this.name = 'TimelineAssetsError';
        this.message = message;
        this.cause = cause;
    }

    TimelineAssetsError.prototype = Object.create(Error.prototype);

    /**
     * @param {Document} doc
     * @returns {{config: Object, clone: function(string): HTMLElement}}
     * @throws {TimelineAssetsError}
     */
    function read(doc) {
        var root = doc.querySelector(ROOT),
            script = root === null ? null : root.querySelector(CONFIG),
            templates = {},
            config;

        if (script === null) {
            throw new TimelineAssetsError('The page does not include the timeline assets');
        }
        try {
            config = timelineConfig.parse(JSON.parse(script.textContent));
        } catch (malformed) {
            throw new TimelineAssetsError('The timeline config is malformed', malformed);
        }
        TEMPLATES.forEach(function (name) {
            var template = root.querySelector('template[data-template="' + name + '"]');

            if (template === null || template.content.children.length === 0) {
                throw new TimelineAssetsError('The timeline template "' + name + '" is missing');
            }
            templates[name] = template;
        });

        return Object.freeze({
            config: config,
            clone: function (name) {
                var template = templates[name];

                if (template === undefined) {
                    throw new TimelineAssetsError('The timeline has no template "' + name + '"');
                }

                return template.content.cloneNode(true).children[0];
            }
        });
    }

    return Object.freeze({
        TEMPLATES: TEMPLATES,
        read: read,
        TimelineAssetsError: TimelineAssetsError
    });
});
