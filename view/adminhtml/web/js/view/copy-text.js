/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Copies a text from its config when the element is clicked, and shows the sibling [data-role="copied"] note
 * for a moment.
 */
define([], function () {
    'use strict';

    var SHOWN_FOR_MS = 1500;

    /**
     * @param {{text: string}} config
     * @param {HTMLElement} element
     */
    return function (config, element) {
        var note = element.parentNode ? element.parentNode.querySelector('[data-role="copied"]') : null,
            timer = null;

        if (typeof config.text !== 'string' || !navigator.clipboard) {
            return;
        }

        element.addEventListener('click', function () {
            navigator.clipboard.writeText(config.text).then(function () {
                if (!note) {
                    return;
                }

                note.hidden = false;
                window.clearTimeout(timer);
                timer = window.setTimeout(function () {
                    note.hidden = true;
                }, SHOWN_FOR_MS);
            }, function () {
                if (note) {
                    note.hidden = true;
                }
            });
        });
    };
});
